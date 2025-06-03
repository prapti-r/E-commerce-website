<!DOCTYPE html>
<html>
<head>
    <title>Wishlist</title>
</head>
<body>
    <h1>Your Wishlist</h1>
    @if (session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p style="color: red;">{{ session('error') }}</p>
    @endif

    @if (empty($items))
        <p>Your wishlist is empty.</p>
    @else
        <p>Total Quantity: {{ $total_quantity }} ({{ $remaining_items }} remaining)</p>
        <p>Subtotal: ${{ $formatted_subtotal }}</p>
        <ul>
            @foreach ($items as $item)
                <li>
                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" width="50">
                    {{ $item['name'] }} - ${{ number_format($item['price'], 2) }} x {{ $item['quantity'] }} = ${{ number_format($item['item_total'], 2) }}
                    (Stock: {{ $item['stock'] }})
                    <form action="{{ route('wishlist.update') }}" method="POST" style="display: inline;">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $item['id'] }}">
                        <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['stock'] }}">
                        <button type="submit">Update</button>
                    </form>
                    <form action="{{ route('wishlist.remove') }}" method="POST" style="display: inline;">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $item['id'] }}">
                        <button type="submit">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <a href="{{ url('/') }}">Continue Shopping</a>
</body>
</html>