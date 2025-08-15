<h1 align="center">Mulago Pharmacy Inventory System - README</h1>

<h3>System Overview</h3>
The Mulago Pharmacy Inventory System is a comprehensive web-based application designed to manage:<br> 
Drug inventory tracking<br>
Sales transactions<br>
User management<br>
Supplier information<br>
Expiry date monitoring<br>

The system features role-based access control with three distinct user roles providing different levels of access.<br>

<h3>User Accounts</h3><br>
The system comes with three pre-configured user accounts for different roles:<br>

<h4>1. Administrator (Full Control)</h4><br>
Username: pharmacy_admin<br>
Password: SecurePharmacy@2025<br>

Permissions:<br>
User management<br>
View sales transactions<br>
All inventory functions (except adding sales)<br>

<h4>2. Pharmacist (Sales Management)<h4><br>
Username: Akram<br>
Password: AkramWalugembe66<br>
<br>
Permissions:<br>
Process sales transactions<br>


<h4>3. Department Staff (Inventory Management)</h4><br>
Username: Essie<br>
Password: Esther123<br>

Permissions:<br>
Manage drug inventory<br>
Update stock levels<br>
Handle drugs & drug categories<br>
Process supplier information<br>

<h3>Features</h3><br>
<h4>Core Modules</h4><br>
Inventory Management<br>
Track drug quantities<br>
Set reorder levels<br>
Expiry date tracking<br>
Sales Processing<br>
Record sales transactions<br>
Process returns (taken and returned)<br>
User Management<br>
Role-based access control<br>
Password management<br>
Activity logging<br>
Expiry alerts<br>
Low stock warnings<br>

<h4>Special Features<h4><br>
Real-time stock alerts<br>

<h4>Troubleshooting Common Issues</h4><br>
**Database Connection Errors**
	Verify database credentials in database.php<br>
	Check MySQL service is running<br>
	Confirm user privileges<br>

**Login Problems<br>**
	Clear browser cache<br>
	Check CAPS LOCK key<br>
	Reset password if needed<br>

**Permission Errors<br>**
	Verify file permissions<br>
	Ensure PHP has proper rights<br>

**Error Messages<br>**
	"Invalid user ID": Typically occurs when trying to modify non-existent users<br>
	"Access denied": Indicates insufficient permissions for the action<br>
	"CSRF token mismatch": Refresh the page and try again<br>
