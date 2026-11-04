# Parking Lot Management
A web app for running a parking lot. Staff log in, check cars in, assign them a spot, check them out, and take payment. The app tracks which spots are free, keeps a history of every visit, and shows revenue reports.

Built with PHP, MySQL, HTML, CSS and JavaScript.

## Features

- Staff login with hashed passwords and two roles: admin and attendant
- Dashboard with a live lot map and a list of parked cars
- Check in: finds the car by plate or adds it if it's new, then creates a ticket
- Check out: works out the fee, takes cash or card, and prints a receipt
- Ticket history with plate search
- View and delete vehicles, add and delete parking spots
- Reports: revenue by spot type, payments by staff, top customers

## How to Run

You need PHP 8 and MySQL 8.

1. Run `sql/schema.sql` in MySQL Workbench. This creates the `parking_lot` database with sample data.
2. Open `config/db.php` and set your MySQL username, password and port.
3. Open a terminal in the project folder and run `php -S localhost:8000`.
4. Go to `http://localhost:8000/login.php`.

## Test Logins

Username  Password   Role      

 admin:    admin123   (Admin)     
 maria:   staff1234  (Attendant) 

Admins can add and delete spots. Attendants can check cars in and out.

## How to Test

1. Log in as `admin`.
2. Click **Check In**, type a new plate, pick a spot and submit. The spot turns red on the dashboard.
3. Try checking in the same plate again. The app blocks it.
4. Click **Check Out**, select the ticket, pick cash or card and submit. A receipt is shown and the spot turns green again.
5. Open **Reports** to see the payment added.
6. Log out and log in as `maria`. The add and delete buttons on the Spots page are hidden.
