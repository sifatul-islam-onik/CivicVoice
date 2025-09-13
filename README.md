
# CivicVoice

**CSE 3120: Information Systems Design Laboratory**

CivicVoice is a community-driven platform that empowers citizens to report local issues such as broken streetlights, garbage, potholes, and more. Users can submit reports with geotagged photos, while local authorities can update the status of each issue (Pending, In Progress, Fixed). This fosters transparency, accountability, and collaboration between citizens and local government.

## Features
- **Report Issues:** Citizens can report local problems with descriptions, geotagged locations, and photos.
- **Status Tracking:** Authorities can update the status of each issue (Pending, In Progress, Fixed).
- **Photo Uploads:** Attach images to provide visual evidence of issues.
- **Community Engagement:** Users can view, comment, and support reported issues.
- **Responsive Design:** Accessible on desktop and mobile devices.

## Tech Stack
- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL

## Getting Started
1. **Clone the repository:**
   ```sh
   git clone https://github.com/sifatul-islam-onik/CivicVoice.git
   ```
2. **Set up your web server:**
   - Place the project files in your web server's root directory (e.g., `htdocs` for XAMPP).
   - Ensure PHP and a database (MySQL/MariaDB) are installed and running.
3. **Configure the database:**
   - Create a new database (e.g., `civicvoice`).
   - Import the provided SQL schema (if available).
   - Update database credentials in the PHP config file (e.g., `config.php`).
4. **Access the application:**
   - Open your browser and navigate to `http://localhost/CivicVoice`.

## Folder Structure
```
CivicVoice/
├── index.html
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── ...
├── api/
│   └── ...
├── config.php
└── ...
```

## Contributing
Contributions are welcome! Please open issues or submit pull requests for improvements.


## Collaborators
- Md Sifatul Islam (2107016)
- Ishrat Binte Ahmed (2107019)
- Md Saif Ahmed Shejan (2107009)

## License
This project is licensed under the MIT License.
