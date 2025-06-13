#  Electricity Bill Management System (EBMS)

A web-based application built for managing electricity bill payments and customer data. The system supports **role-based dashboards** for both users and administrators, with powerful features like **automated billing**, **payment tracking**, **visual analytics** and **real-time notifications**.

##  Features

###  Authentication
- Role-based login system (Admin/User)
- Secure session management
- Prevents unauthorized access

### User Dashboard
- View current and past bills
- Download payment receipts
- Track usage via bar charts
- Receive energy-saving tips
- Earn reward points for timely payments
- View notifications and payment status

###  Admin Dashboard
- Create user accounts with auto-generated Meter ID
- Enter meter readings and generate bills
- Adjust tariff rates and payment rules
- Send reminders for unpaid bills
- Verify or override payments

###  Billing & Payments
- Auto-generated bills based on readings and tariff
- Track and update payment history
- Generates unique transaction IDs
- Real-time balance and due date info

###  Analytics & Notifications
- Visualize monthly power consumption with **Chart.js**
- Admin can send real-time notifications with timestamps
- Reward points system for regular bill payers


##  Technologies Used

| Frontend      | Backend          | Tools & Database   |
|---------------|------------------|--------------------|
| HTML, CSS, JS | PHP (Core)       | MySQL, XAMPP       |
| Chart.js      | PHPMyAdmin       | Apache Server      |

---

##  DBMS Concepts Used

- **ER Modeling:** Defined entities like User, Bills, Tariff, Notifications
- **Normalization:** Reduced redundancy across related tables
- **Relational DB Model:** Primary & foreign keys ensure data integrity
- **Security:** Authentication and role-based access
- **SQL Queries:** Used for CRUD operations and bill generation logic

