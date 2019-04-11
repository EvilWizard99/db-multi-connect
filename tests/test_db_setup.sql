# create the test database
CREATE DATABASE IF NOT EXISTS ewc_test_database;

# create a user with access to the test database
GRANT ALL PRIVILEGES ON ewc_test_database.* TO 'ewc_test_user'@'localhost' IDENTIFIED BY 'ewc_test_user_password';
