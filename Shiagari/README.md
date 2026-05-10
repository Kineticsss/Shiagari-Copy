# SHIAGARI

> **Rise to the challenge**

SHIAGARI is a comprehensive project management and collaboration platform designed to help teams organize ideas, track progress, and collaborate effectively on shared projects.

## 🎯 Features

### Core Modules

- **Projects Board** - Centralized dashboard for managing all active projects
- **Idea Factory** - Brainstorming and idea sharing platform for creative collaboration
- **Progress Tracker** - Real-time progress monitoring and status updates
- **Roadmap** - Strategic planning and milestone visualization
- **Post Board** - Discussion forum for project-related conversations
- **Chats** - Direct messaging between team members
- **Profile Management** - User account customization and settings

### Additional Features

- **User Authentication** - Secure login, registration, and logout
- **File Upload** - Document and file storage integration
- **Session Management** - Secure session handling with CSRF protection
- **Real-time Updates** - Live data synchronization across features

## 🛠️ Tech Stack

- **Backend:** PHP
- **Database & Auth:** Firebase (Realtime Database, Authentication, Storage)
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Icons:** Font Awesome 6
- **Fonts:** Google Fonts (Inter)

## 📁 Project Structure

```
shiagari/
├── index.php                 # Main entry point / Login page
├── auth/
│   ├── login.php            # Login handler
│   ├── register.php         # Registration handler
│   └── logout.php           # Logout handler
├── config/
│   ├── firebase.php         # Firebase API configuration
│   ├── http.php             # HTTP utilities
│   └── session.php          # Session management & CSRF protection
├── landing/                 # Projects dashboard
│   ├── landing.html
│   ├── landing.css
│   └── landing.js
├── idea/                    # Idea Factory module
│   ├── idea.html
│   ├── idea.css
│   └── idea.js
├── progress/                # Progress Tracker module
│   ├── progress.html
│   ├── progress.css
│   └── progress.js
├── roadmap/                 # Roadmap module
│   ├── roadmap.html
│   ├── roadmap.css
│   └── roadmap.js
├── postboard/               # Post Board module
│   ├── postboard.html
│   ├── postboard.css
│   └── postboard.js
├── message/                 # Chat module
│   ├── message.html
│   ├── message.css
│   └── message.js
├── profile/                 # User Profile module
│   ├── profile.html
│   ├── profile.css
│   └── profile.js
├── storage/                 # File upload handler
│   └── upload.php
└── .env                     # Environment variables (Firebase config)
```

## 🚀 Getting Started

### Prerequisites

- PHP 7.4 or higher
- Firebase project with:
  - Realtime Database
  - Authentication enabled
  - Storage bucket configured
- Web server (Apache, Nginx, etc.)
- cURL extension enabled for PHP

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Shiagari
   ```

2. **Configure Firebase**
   - Create a `.env` file in the project root
   - Add your Firebase credentials:
     ```
     FIREBASE_PROJECT_ID=your_project_id
     FIREBASE_API_KEY=your_api_key
     FIREBASE_STORAGE_BUCKET=your_storage_bucket
     ```

3. **Set up your web server**
   - Point your web server to the project root
   - Ensure PHP is configured to serve `.php` files

4. **Access the application**
   - Navigate to `http://localhost` (or your configured domain)
   - Register a new account or login

## 📝 Usage

### Creating a Project
1. Navigate to **Projects** from the sidebar
2. Click **Create New Project**
3. Enter project details (name, description, deadline)
4. Submit to add to your dashboard

### Sharing Ideas
1. Go to **Idea Factory**
2. Click **Submit New Idea**
3. Describe your idea and add relevant details
4. Ideas can be discussed and developed collaboratively

### Tracking Progress
1. Open **Progress Tracker**
2. Add tasks or milestones for your projects
3. Update status as work progresses
4. Monitor team contributions in real-time

### Planning with Roadmaps
1. Create roadmaps in the **Roadmap** section
2. Define phases, milestones, and deliverables
3. Assign team members to roadmap items
4. Track project timelines visually

### Team Communication
- **Post Board:** Share updates and discuss project matters
- **Chats:** Send direct messages to team members
- **Profile:** View and update your profile information

## 🔒 Security Features

- **CSRF Protection** - Secure token generation and validation
- **Session Management** - Secure session handling
- **Firebase Authentication** - Built-in user authentication
- **Authorization** - Role-based access control

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Create a new branch for your feature
2. Make your changes with clear, descriptive commits
3. Test thoroughly before submitting
4. Submit a pull request with a detailed description

## 📄 License

This project is part of a school assignment. Please check with your instructor regarding usage rights.

## 📞 Support

For issues or questions:
- Check existing documentation
- Review the project code comments
- Contact the project maintainers

---

**Built with ❤️ for collaboration and project management**
