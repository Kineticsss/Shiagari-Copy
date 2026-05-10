<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/http.php';
start_secure_session();
if (empty($_SESSION['uid']) || empty($_SESSION['token'])) {
    header('Location: ../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>SHIAGARI · Roadmap</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="roadmap.css">
</head>
<body>

  <aside class="sidebar">
    <h1>SHIAGARI</h1>
    <nav>
      <a href="../landing/landing.html"><i class="fas fa-project-diagram"></i> <span>Projects</span></a>
      <a href="../idea/idea.php"><i class="fas fa-lightbulb"></i> <span>Idea Factory</span></a>
      <a href="../progress/progress.php"><i class="fas fa-chart-line"></i> <span>Progress Tracker</span></a>
      <a href="../roadmap/roadmap.php" class="active"><i class="fas fa-map"></i> <span>Roadmap</span></a>
      <a href="../postboard/postboard.php"><i class="fas fa-newspaper"></i> <span>Post Board</span></a>
      <a href="../message/message.php"><i class="fas fa-comments"></i> <span>Chats</span></a>
    </nav>
    <div class="project-sidebar-section">
      <div class="project-sidebar-label">Current Project</div>
      <select id="projectSelect" class="project-sidebar-select">
        <option value="project1">Dashboard Redesign</option>
        <option value="project2">Mobile App Launch</option>
        <option value="project3">API Integration</option>
      </select>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="title"><i class="fas fa-map"></i> ROADMAP</h1>
        <div class="project-context">Browsing: <strong id="currentProjectName">Dashboard Redesign</strong></div>
      </div>
      <div class="topbar-actions">
        <div class="stats-badge">
          <i class="fas fa-calendar-alt"></i>
          <span id="timelineSpan">0 epics</span>
        </div>
        <a href="../profile/profile.html" class="profile-button" title="Profile"><span>U</span></a>
      </div>
    </div>

    <div class="timeline-container">
      <div class="timeline-header">
        <div class="label-placeholder"></div>
        <div class="quarter-labels">
          <span>Q1</span>
          <span>Q2</span>
          <span>Q3</span>
          <span>Q4</span>
        </div>
      </div>

      <div class="timeline" id="timeline">
        <!-- Dynamic roadmap items will be injected here -->
      </div>
    </div>

    <div class="action-buttons">
      <button class="btn btn-primary" id="addEpicBtn">
        <i class="fas fa-plus"></i> Add Epic
      </button>
      <button class="btn btn-secondary" id="viewTasksBtn">
        <i class="fas fa-tasks"></i> View All
      </button>
    </div>
  </main>

  <!-- Add/Edit Epic Modal -->
  <div class="modal" id="epicModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle"><i class="fas fa-rocket"></i> Add Epic</h3>
        <button class="modal-close" id="closeModalBtn">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label><i class="fas fa-heading"></i> Epic Name</label>
          <input type="text" id="epicName" placeholder="e.g., Authentication System" maxlength="40">
        </div>
        <div class="form-group">
          <label><i class="fas fa-palette"></i> Color</label>
          <select id="epicColor">
            <option value="pink">🌸 Pink (UI/UX)</option>
            <option value="blue">💙 Blue (Frontend)</option>
            <option value="red">❤️ Red (Backend)</option>
            <option value="green">💚 Green (DevOps)</option>
            <option value="purple">💜 Purple (Database)</option>
            <option value="orange">🧡 Orange (Testing)</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-sliders-h"></i> Duration (quarters)</label>
          <input type="range" id="durationSlider" min="1" max="4" value="2" step="1">
          <div class="slider-value" id="durationValue">2 quarters</div>
        </div>
        <div class="form-group">
          <label><i class="fas fa-calendar-week"></i> Start Quarter</label>
          <select id="startQuarter">
            <option value="0">Q1</option>
            <option value="1" selected>Q2</option>
            <option value="2">Q3</option>
            <option value="3">Q4</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-align-left"></i> Description</label>
          <textarea id="epicDesc" rows="2" placeholder="What does this epic include?"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" id="cancelModalBtn">Cancel</button>
        <button class="btn-primary" id="saveEpicBtn">Save Epic</button>
      </div>
    </div>
  </div>

  <!-- View All Modal -->
  <div class="modal" id="tasksModal">
    <div class="modal-content tasks-modal">
      <div class="modal-header">
        <h3><i class="fas fa-tasks"></i> All Epics</h3>
        <button class="modal-close" id="closeTasksModalBtn">&times;</button>
      </div>
      <div class="modal-body" id="tasksModalBody">
        <!-- Dynamic tasks list -->
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" id="closeTasksFooterBtn">Close</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toastMsg">
    <i class="fas fa-check-circle"></i>
    <span id="toastText">Epic saved!</span>
  </div>

  <script src="roadmap.js"></script>
</body>
</html>