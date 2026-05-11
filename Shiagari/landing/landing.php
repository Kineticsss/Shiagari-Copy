<?php
require_once __DIR__ . '/../config/session.php';

start_secure_session();
$csrfToken = csrf_token();

// Redirect to login if not authenticated
if (!isset($_SESSION['uid']) || $_SESSION['uid'] === '') {
    // Clear any potentially corrupted session data
    $_SESSION = [];
    session_destroy();
    header('Location: ../index.php?no_redirect=1');
    exit;
}

// Enforce Firestore profile existence as landing condition.
require_once __DIR__ . '/../api/user-profile.php';

$uid = (string)($_SESSION['uid'] ?? '');
$token = (string)($_SESSION['token'] ?? '');

// If Firestore profile doesn't exist yet, don't kick the user back to login.
// Create it lazily from session values when missing.
$profileCheck = get_user_profile($uid, $token);
if (!$profileCheck['success'] || empty($profileCheck['profile'])) {
    require_once __DIR__ . '/../api/user-profile.php';

    // Best-effort creation: landing should be reachable even if profile creation is delayed.
    $fullName = (string)($_SESSION['full_name'] ?? '');
    $username = (string)($_SESSION['username'] ?? '');
    $email = (string)($_SESSION['email'] ?? '');

    // Save user profile (ignore result; next API calls can reconcile)
    try {
        // save_user_profile signature: (uid, email, fullName, username, idToken)
        if (function_exists('save_user_profile') && !empty($email)) {
            save_user_profile($uid, $email, $fullName, $username, $token);
        }
    } catch (Throwable $e) {
        // Intentionally swallow; user should not be logged out due to profile race conditions
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>SHIAGARI · Projects Board</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="landing.css">
</head>
<body>

  <aside class="sidebar">
    <h1>SHIAGARI</h1>
    <nav>
      <a href="../landing/landing.php" class="active"><i class="fas fa-project-diagram"></i> <span>Projects</span></a>
      <a href="../idea/idea.php"><i class="fas fa-lightbulb"></i> <span>Idea Factory</span></a>
      <a href="../progress/progress.php"><i class="fas fa-chart-line"></i> <span>Progress Tracker</span></a>
      <a href="../roadmap/roadmap.php"><i class="fas fa-map"></i> <span>Roadmap</span></a>
      <a href="../postboard/postboard.php"><i class="fas fa-newspaper"></i> <span>Post Board</span></a>
      <a href="../message/message.php"><i class="fas fa-comments"></i> <span>Chats</span></a>
    </nav>
  </aside>

  <main class="main">
    <header class="topbar">
      <h2>Projects</h2>
      <div class="topbar-actions">
        <div class="stats-badge">
          <i class="fas fa-rocket"></i>
          <span id="projectCount">0</span> active projects
        </div>
        <a href="../profile/profile.html" class="profile-button" title="Profile"><span>U</span></a>
      </div>
    </header>

    <section class="projects" id="projectsGrid"></section>
  </main>

  <!-- Add Project Modal -->
  <div class="modal" id="projectModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-plus-circle"></i> Create New Project</h3>
        <button class="modal-close" id="closeModalBtn">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label><i class="fas fa-tag"></i> Project Name</label>
          <input type="text" id="projectName" placeholder="e.g., AI Dashboard" maxlength="40">
        </div>
        <div class="form-group">
          <label><i class="fas fa-align-left"></i> Description</label>
          <textarea id="projectDesc" rows="3" placeholder="Short description..."></textarea>
        </div>
        <div class="form-group">
          <label><i class="fas fa-users"></i> Collaborators</label>
          <input type="text" id="projectMembers" placeholder="Add people separated by commas">
        </div>
        <div class="form-group">
          <label><i class="fas fa-chart-simple"></i> Status</label>
          <select id="projectStatus">
            <option value="active">🚀 Active</option>
            <option value="planning">📋 Planning</option>
            <option value="hold">⏸ On Hold</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" id="cancelModalBtn">Cancel</button>
        <button class="btn-primary" id="saveProjectBtn">Create Project</button>
      </div>
    </div>
  </div>

  <!-- Project Details Modal -->
  <div class="modal" id="projectDetailsModal">
    <div class="modal-content project-details-content">
      <div class="modal-header">
        <div>
          <h3 id="detailsTitle">Project Name</h3>
          <div class="details-meta"><span id="detailsStatus">Active</span> · <span id="detailsCreated">Today</span></div>
        </div>
        <button class="modal-close" id="closeDetailsBtn">&times;</button>
      </div>
      <div class="modal-body project-details-body">
        <div class="details-section">
          <h4>Description</h4>
          <p id="detailsDescription">Project description goes here.</p>
        </div>
        <div class="details-grid">
          <div class="details-card">
            <h4>Author</h4>
            <p id="detailsAuthor">Project owner</p>
          </div>
          <div class="details-card">
            <h4>Members</h4>
            <ul id="detailsMembers"></ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toastMsg">
    <i class="fas fa-check-circle"></i>
    <span id="toastText">Project created!</span>
  </div>

  <script>
    const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  </script>
  <script src="../js/api-client.js"></script>
  <script src="landing.js"></script>
</body>
</html>