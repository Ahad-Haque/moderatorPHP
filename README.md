# Moderator System

A comprehensive PHP-based member management system for cooperative societies and organizations. This system provides tools for managing member details, financial records, and deposit calculations.

## Features

### 🔐 Authentication

- Secure login system for moderators
- Session management
- Role-based access control

### 👥 Member Management

- **Member Basic Details**: View and manage member information
- **Member Advanced Details**: Detailed financial records per member
- **Add New Members**: Easy member registration with form validation
- **Member Deletion**: Secure member removal with confirmation

### 💰 Financial Management

- **Deposit Calculation**: Track shares, deposits, and various fees
- **Service Charge Calculation**: Automatic 20% service charge for members with <5 years membership
- **Date Range Reports**: Generate financial reports for specific periods
- **Real-time Totals**: Automatic calculation of column and grand totals

### 📊 Reporting

- **All Users Report**: Comprehensive overview of all members
- **Printable Reports**: Print-friendly layouts for documentation
- **Financial Summaries**: Detailed breakdowns of deposits and costs

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Custom PHP with WordPress integration
- **AJAX**: For dynamic data loading and form submissions

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- WordPress installation
- Web server (Apache/Nginx)

### Setup Steps

1. **Clone the repository**

   ```bash
   git clone https://github.com/yourusername/moderator-system.git
   cd moderator-system
   ```

2. **Database Configuration**

   ```php
   // Update database credentials in moderator.php
   $conn = new mysqli('localhost', 'username', 'password', 'database_name');
   ```

3. **Create Required Tables**

   ```sql
   -- Moderator credentials table
   CREATE TABLE moderatorCredential (
       id INT AUTO_INCREMENT PRIMARY KEY,
       adminID VARCHAR(50) UNIQUE,
       adminPassword VARCHAR(255)
   );

   -- Member basic details table
   CREATE TABLE memberBasicDetails (
       no INT AUTO_INCREMENT PRIMARY KEY,
       id_no VARCHAR(20) UNIQUE,
       name VARCHAR(100),
       designation VARCHAR(50),
       fathers_name VARCHAR(100),
       address TEXT,
       mobile_no VARCHAR(15),
       admit_date DATE,
       resign_date DATE NULL
   );

   -- Deposit calculation table
   CREATE TABLE depositCalculation (
       serial_no INT AUTO_INCREMENT PRIMARY KEY,
       entry_date DATE,
       range_start DATE,
       range_end DATE,
       share DECIMAL(10,2) DEFAULT 0.00,
       deposit DECIMAL(10,2) DEFAULT 0.00,
       asholAday DECIMAL(10,2) DEFAULT 0.00,
       shudAday DECIMAL(10,2) DEFAULT 0.00,
       serviceCharge DECIMAL(10,2) DEFAULT 0.00,
       nirbachonFee DECIMAL(10,2) DEFAULT 0.00,
       vortiFee DECIMAL(10,2) DEFAULT 0.00,
       bibidhFee DECIMAL(10,2) DEFAULT 0.00,
       bankShulko DECIMAL(10,2) DEFAULT 0.00,
       ogrimDenaLbc DECIMAL(10,2) DEFAULT 0.00,
       row_total DECIMAL(10,2) GENERATED ALWAYS AS
           (share + deposit + asholAday + shudAday + serviceCharge +
            nirbachonFee + vortiFee + bibidhFee + bankShulko + ogrimDenaLbc) STORED
   );
   ```

4. **File Permissions**

   ```bash
   chmod 755 moderator.php
   chmod -R 755 moderatorModules/
   ```

5. **Initial Admin Setup**
   ```sql
   INSERT INTO moderatorCredential (adminID, adminPassword)
   VALUES ('admin', 'your_secure_password');
   ```

## File Structure

```
moderator-system/
├── moderator.php                 # Main entry point and dashboard
├── moderatorModules/
│   ├── memberBasicDetails.php    # Member listing and basic info
│   ├── memberAdvancedDetails.php # Individual member financial details
│   ├── add_member.php           # AJAX endpoint for adding members
│   ├── allUsers.php             # Comprehensive user report
│   ├── depositCalculation.php   # Financial calculations and reports
│   ├── fetch_totals.php         # AJAX endpoint for date range totals
│   └── loadingModule.php        # Loading animations and utilities
└── README.md
```

## Usage

### 1. Login

- Access the system via `moderator.php`
- Enter your administrator credentials
- Navigate through the dashboard options

### 2. Managing Members

- **View All Members**: Click "User Details" to see member list
- **Add New Member**: Click the "+" button and fill in the form
- **View Member Details**: Click on any member row for detailed view
- **Update Financial Records**: Add entries for shares, deposits, and fees

### 3. Financial Reports

- **Date Range Reports**: Use "Deposit Calculation" with custom date ranges
- **Print Reports**: Use the print button for physical documentation
- **Export Data**: Print-friendly formats for external use

## API Endpoints

### AJAX Endpoints

- `POST /moderatorModules/add_member.php` - Add new member
- `POST /moderatorModules/fetch_totals.php` - Get financial totals for date range
- `GET /moderator.php?module=memberAdvancedDetails&action=delete` - Delete member

### Parameters

```javascript
// Add member example
{
    "id_no": "MEM001",
    "name": "John Doe",
    "designation": "Manager",
    "fathers_name": "Father Name",
    "address": "Full Address",
    "mobile_no": "1234567890",
    "admit_date": "2024-01-01",
    "resign_date": null // Optional
}
```

## Security Features

- **SQL Injection Protection**: Prepared statements used throughout
- **XSS Prevention**: HTML escaping with `htmlspecialchars()`
- **Session Management**: Secure session handling
- **Access Control**: Login required for all operations
- **Data Validation**: Server-side and client-side validation

## Business Logic

### Service Charge Calculation

- Members with <5 years membership: 20% service charge on total deposits
- Calculated automatically based on admit and resign dates
- Applied only to resigned members

### Dynamic Table Creation

- Individual tables created for each member using their ID
- Tables created on-demand when first financial entry is made
- Naming convention: Replace hyphens with underscores in member ID

## Browser Compatibility

- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/new-feature`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/new-feature`)
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:

- Create an issue on GitHub
- Email: [your-email@domain.com]

## Changelog

### Version 1.0.0

- Initial release
- Member management system
- Financial tracking
- Report generation
- Print functionality

## Known Issues

- Date range calculations require both start and end dates
- Print layouts optimized for landscape orientation
- Service charge calculation requires manual refresh in some browsers

## Future Enhancements

- [ ] Export to Excel/CSV functionality
- [ ] Email notifications for member activities
- [ ] Backup and restore functionality
- [ ] Multi-language support
- [ ] Advanced reporting dashboard
- [ ] Mobile responsive design improvements

---

**Note**: This system is designed for cooperative societies and similar organizations requiring member and financial management capabilities.
