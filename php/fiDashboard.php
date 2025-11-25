<?php
require_once 'auth_check.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Intern Dashboard</title>
  <link rel="stylesheet" href="../css/dashboardFI.css">
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Attendance System</h2>
      <nav>
        <ul>
          <li><a href="link" class="active">Course List</a></li>
          <li><a href="link">Sessions</a></li>
          <li><a href="link">Reports</a></li>
          <li><a href="link">Manage Students</a></li>
          <li><a href= "../php/logout.php"> Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <h1>Welcome, Faculty Intern!</h1>
      </header>

      <section class="section">
        <h2>Course List</h2>
        <table>
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Title</th>
              <th>Students</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>CSC101</td>
              <td>Introduction to Programming</td>
              <td>35</td>
              <td><button>View</button></td>
            </tr>
            <tr>
              <td>MTH120</td>
              <td>Discrete Mathematics</td>
              <td>42</td>
              <td><button>View</button></td>
            </tr>
          </tbody>
        </table>
      </section>
    </main>

  </div>
</body>
</html>