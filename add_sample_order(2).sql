-- Run this against your uivent database in phpMyAdmin (SQL tab) or mysql CLI.
-- It only adds the missing sample order so order_id = 1 exists — it won't
-- touch anything else in your database.
INSERT INTO orders(user_id,total_amount,status) VALUES
(1,28.00,'Pending Payment');

INSERT INTO order_items(order_id,product_id,quantity,price) VALUES
(1,2,1,28.00);
