<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\User;
// use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\Mail;
// use App\Mail\OtpMail;
// use Carbon\Carbon;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Show the signup form.
     *
     * @return \Illuminate\View\View
     */
    public function showSignupForm()
    {
        return view('signup');
    }

    /**
     * Show the signin form.
     *
     * @return \Illuminate\View\View
     */
    public function showSigninForm()
    {
        return view('signin');
    }

    /**
     * Handle signup form submission and send OTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function signup(Request $request)
    {
        // Validate the form input
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:USER1,email|max:255',
            'contact_no' => 'required|numeric|digits:10',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:customer,trader',
        ]);

        // Generate a unique user_id
        $userId = User::generateUserId();

        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes(10);

        // Set admin_verified based on user type (Y for customer, N for trader)
        $adminVerified = strtoupper($validated['role'] === 'customer' ? 'Y' : 'N');

        // Create the user
        $user = User::create([
            'user_id' => $userId,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'user_type' => $validated['role'],
            'email' => $validated['email'],
            'contact_no' => $validated['contact_no'],
            'password' => Hash::make($validated['password']),
            'otp' => $otp,
            'is_verified' => false,
            'otp_expires_at' => $otpExpiresAt,
            'admin_verified' => $adminVerified,
        ]);

        // Send OTP email
        Mail::to($user->email)->send(new OtpMail($otp));

        return redirect()->route('verify.otp.form', ['email' => $user->email])
                        ->with('success', 'Please check your email for the OTP to verify your account.');
    }

    /**
     * Show the OTP verification form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function showVerifyOtpForm(Request $request)
    {
        $email = $request->query('email');
        return view('verify-otp', ['email' => $email]);
    }

    /**
     * Verify the OTP and activate the user account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:USER1,email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if OTP is valid and not expired
        if ($user->otp != $request->otp) {
            return redirect()->back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return redirect()->back()->withErrors(['otp' => 'OTP has expired.']);
        }

        // Mark user as verified and clear OTP data
        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        return redirect()->route('signin')->with('success', 'Email verified successfully! Please sign in.');
    }

    //for sign in
    public function signin(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:USER1,email',
            'password' => 'required|string|min:8',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        // Verify password first
        if (!Hash::check($request->password, $user->password)) {
            return redirect()->back()->withErrors(['password' => 'Invalid password.']);
        }
    
        // Different authentication flow for customers and traders
        if ($user->user_type === 'customer') {
            // For customers, check email verification
            if (!$user->is_verified) {
                return redirect()->back()->withErrors(['email' => 'Please verify your email before signing in.']);
            }
        } else if ($user->user_type === 'trader') {
            // For traders, check admin verification first
            if (strtoupper($user->admin_verified) !== 'Y') {
                return redirect()->back()->withErrors(['email' => 'Your trader account is pending admin approval.']);
            }
            // No need to check email verification again as it was done during signup
        }
    
        // Set up session
        session(['user_id' => $user->user_id, 'user_type' => $user->user_type]);
    
        // Handle cart transfer if exists
        if (session()->has('cart')) {
            app(CartController::class)->transferSessionCartToDatabase($user->user_id);
        }
        // Handle wishlist transfer if exists
            if (session()->has('wishlist')) {
                app(WishlistController::class)->transferSessionWishlistToDatabase($user->user_id);
            }
    
        // Route based on user type
        if ($user->user_type === 'customer') {
            return redirect()->route('home')->with('success', 'Signed in successfully!');
        } elseif ($user->user_type === 'trader') {
            return redirect()->route('trader')->with('success', 'Signed in successfully!');
        }
    
        return redirect()->route('home')->with('info', 'Signed in successfully.');
    }
    
    

    //for resending the otp 
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:USER1,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return redirect()->route('signin')->with('success', 'Email already verified. Please sign in.');
        }

        // Generate new OTP
        $otp = rand(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes(10);

        // Update user with new OTP
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => $otpExpiresAt,
        ]);

        // Send new OTP email
        Mail::to($user->email)->send(new OtpMail($otp));

        return redirect()->route('verify.otp.form', ['email' => $user->email])
                        ->with('success', 'New OTP has been sent to your email.');
    }

    public function logout(Request $request)
    {
        // Clear the session
        $request->session()->forget('user_id');
        $request->session()->forget('user_type');

        return response()->json(['success' => true]);
    }
}