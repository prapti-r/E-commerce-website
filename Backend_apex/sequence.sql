DROP SEQUENCE seq_userid;
DROP SEQUENCE seq_shopid;
DROP SEQUENCE seq_reportid;
DROP SEQUENCE seq_cartid;
DROP SEQUENCE seq_cuponid;
DROP SEQUENCE seq_categoryid;
DROP SEQUENCE seq_discountid;
DROP SEQUENCE seq_slotid;
DROP SEQUENCE seq_productid;
DROP SEQUENCE seq_orderid;
DROP SEQUENCE seq_wishlistid;
DROP SEQUENCE seq_reviewid;
DROP SEQUENCE seq_paymentid;
DROP SEQUENCE seq_rfid;

-- SEQUENCE FOR ALL THE TABLE

-- Sequence for USER1 table
CREATE SEQUENCE seq_userid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for SHOP table
CREATE SEQUENCE seq_shopid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the REPORT table
CREATE SEQUENCE seq_reportid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the CART table
CREATE SEQUENCE seq_cartid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the CUPON table
CREATE SEQUENCE seq_cuponid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the CATEGORY table
CREATE SEQUENCE seq_categoryid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the DISCOUNT table
CREATE SEQUENCE seq_discountid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the DISCOUNT table
CREATE SEQUENCE seq_slotid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the PRODUCT table
CREATE SEQUENCE seq_productid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the ORDER1 table
CREATE SEQUENCE seq_orderid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the WISHLIST table
CREATE SEQUENCE seq_wishlistid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Ssequence for the REVIEW table
CREATE SEQUENCE seq_reviewid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for the PAYMENT table 
CREATE SEQUENCE seq_paymentid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
    
-- Sequence for RFID_READ table
CREATE SEQUENCE seq_rfid
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;


---Triggers for all thE sequence 

-- Trigger for the USER1 table sequence
CREATE OR REPLACE TRIGGER trg_userid
BEFORE INSERT ON USER1
FOR EACH ROW
BEGIN
    :NEW.user_id := 'user' || TO_CHAR(seq_userid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the SHOP table sequence
CREATE OR REPLACE TRIGGER trg_shopid
BEFORE INSERT ON SHOP
FOR EACH ROW
BEGIN
    :NEW.shop_id := 'shop' || TO_CHAR(seq_shopid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the REPORT table sequence
CREATE OR REPLACE TRIGGER trg_recordid
BEFORE INSERT ON REPORT
FOR EACH ROW
BEGIN
    :NEW.report_id := 'rep' || TO_CHAR(seq_reportid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the CART table sequence
CREATE OR REPLACE TRIGGER trg_cartid
BEFORE INSERT ON CART
FOR EACH ROW
BEGIN
    :NEW.cart_id := 'cart' || TO_CHAR(seq_cartid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the COUPON table sequence
CREATE OR REPLACE TRIGGER trg_cuponid
BEFORE INSERT ON COUPON
FOR EACH ROW
BEGIN
    :NEW.coupon_id := 'cup' || TO_CHAR(seq_cuponid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the CATEGORY table sequence
CREATE OR REPLACE TRIGGER trg_categoryid
BEFORE INSERT ON CATEGORY
FOR EACH ROW
BEGIN
    :NEW.category_id := 'cat' || TO_CHAR(seq_categoryid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the DISCOUNT table sequence
CREATE OR REPLACE TRIGGER trg_discountid
BEFORE INSERT ON DISCOUNT
FOR EACH ROW
BEGIN
    :NEW.discount_id := 'dis' || TO_CHAR(seq_categoryid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the COLLECTION_SLOT table sequence
CREATE OR REPLACE TRIGGER trg_slotid
BEFORE INSERT ON COLLECTION_SLOT
FOR EACH ROW
BEGIN
    :NEW.slot_id := 'slo' || TO_CHAR(seq_slotid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the PRODUCT table sequence
CREATE OR REPLACE TRIGGER trg_productid
BEFORE INSERT ON PRODUCT
FOR EACH ROW
BEGIN
    :NEW.product_id := 'pro' || TO_CHAR(seq_productid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the ORDER1 TABLE
CREATE OR REPLACE TRIGGER trg_orderid
BEFORE INSERT ON ORDER1
FOR EACH ROW 
BEGIN
    :NEW.order_id := 'ord' || TO_CHAR(seq_orderid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the WISHLIST TABLE
CREATE OR REPLACE TRIGGER trg_wishlistid
BEFORE INSERT ON WISHLIST
FOR EACH ROW
BEGIN
    :NEW.wishlist_id := 'wis' || TO_CHAR(seq_wishlistid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the REVIEW table 
CREATE OR REPLACE TRIGGER trg_reviewid
BEFORE INSERT ON REVIEW 
FOR EACH ROW
BEGIN 
    :NEW.review_id := 'rev' || TO_CHAR(seq_reviewid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the PAYMENT table 
CREATE OR REPLACE TRIGGER trg_paymentid
BEFORE INSERT ON PAYMENT
FOR EACH ROW
BEGIN 
    :NEW.payment_id := 'rev' || TO_CHAR(seq_paymentid.NEXTVAL, 'FM0000');
END;
/

-- Trigger for the RFID_READ  table 
CREATE OR REPLACE TRIGGER trg_rfid_id
BEFORE INSERT ON RFID_READ
FOR EACH ROW
BEGIN 
    :NEW.rfid_id := 'rif' || TO_CHAR(seq_rfid.NEXTVAL, 'FM0000');
END;
/
