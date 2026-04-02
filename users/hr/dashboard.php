<?php 
include '../../global/header.php'; 
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    #wrapper { overflow-x: hidden; }
    #page-content-wrapper { min-width: 100vw; }
    .cursor-pointer { cursor: pointer; }
    
    #reportrange {
        min-width: 280px;
        white-space: nowrap;
        overflow: hidden;
    }

    #date-display {
        display: inline-block;
        max-width: 220px;
        text-overflow: ellipsis;
        overflow: hidden;
        vertical-align: middle;
    }

    @media (min-width: 768px) {
        #page-content-wrapper { min-width: 0; width: 100%; }
    }
</style>

<div class="d-flex" id="wrapper">
    <?php include '../../global/sidebar.php'; ?>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 px-4 mb-4 shadow-sm">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <span class="material-symbols-outlined me-2 text-success">dashboard</span>
                    <h4 class="mb-0 fw-bold">HR Dashboard</h4>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3">
                        <label class="small text-muted mb-2 fw-medium">Filter by Date Range</label>
                        <div id="reportrange" class="d-flex align-items-center justify-content-between bg-light p-2 rounded cursor-pointer border">
                            <div class="d-flex align-items-center">
                                <span class="material-symbols-outlined me-2 fs-5 text-secondary">calendar_month</span>
                                <span class="small" id="date-display"></span>
                            </div>
                            <span class="material-symbols-outlined fs-5">expand_more</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-success border-4">
                        <p class="text-muted small mb-1">Total Employees</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold mb-0">124</h3>
                            <span class="material-symbols-outlined text-success opacity-50 fs-1">badge</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-primary border-4">
                        <p class="text-muted small mb-1">New Applicants</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold mb-0">45</h3>
                            <span class="material-symbols-outlined text-primary opacity-50 fs-1">person_add</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-dark border-4">
                        <p class="text-muted small mb-1">Total Payroll (Monthly)</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold mb-0">₱1.2M</h3>
                            <span class="material-symbols-outlined text-dark opacity-50 fs-1">payments</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-3 border-start border-warning border-4">
                        <p class="text-muted small mb-1">Attendance Today</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="fw-bold mb-0">98%</h3>
                            <span class="material-symbols-outlined text-warning opacity-50 fs-1">event_available</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4 g-3">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold mb-3">Payroll Trend (Last 6 Months)</h6>
                        <canvas id="payrollChart" height="100"></canvas>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold mb-3">Attendance Overview (Weekly)</h6>
                        <canvas id="attendanceChart" height="150"></canvas>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between">
                            <h5 class="fw-bold mb-0">Attendance Report</h5>
                            <button class="btn btn-sm btn-outline-success">Export CSV</button>
                        </div>
                        <div class="table-responsive p-3">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>JC Que</strong></td>
                                        <td>08:00 AM</td>
                                        <td>05:00 PM</td>
                                        <td><span class="badge bg-success-subtle text-success">Present</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Beaver Abellera</strong></td>
                                        <td>08:15 AM</td>
                                        <td>05:00 PM</td>
                                        <td><span class="badge bg-warning-subtle text-warning">Late</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0">Recent Applicants</h5>
                        </div>
                        <div class="p-3">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <p class="mb-0 fw-bold small">Jemmanuel Cyril</p>
                                        <span class="text-muted smaller">Full-stack Dev</span>
                                    </div>
                                    <span class="badge bg-success-subtle text-success">Shortlisted</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <p class="mb-0 fw-bold small">Beaver Abellera</p>
                                        <span class="text-muted smaller">Security Specialist</span>
                                    </div>
                                    <span class="badge bg-warning-subtle text-warning">Pending</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    </div> 
</div> 

<script src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Line Graph - Payroll Trends
const ctxPayroll = document.getElementById('payrollChart').getContext('2d');
new Chart(ctxPayroll, {
    type: 'line',
    data: {
        labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
        datasets: [{
            label: 'Total Disbursement (₱)',
            data: [950000, 1100000, 1400000, 1150000, 1200000, 1250000],
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            fill: true,
            tension: 0.4
        }]
    }
});

// Bar Graph - Attendance
const ctxAttendance = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctxAttendance, {
    type: 'bar',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        datasets: [{
            label: 'Present',
            data: [120, 118, 122, 115, 110],
            backgroundColor: '#198754'
        }, {
            label: 'Late',
            data: [4, 6, 2, 8, 14],
            backgroundColor: '#ffc107'
        }]
    }
});
</script>
<script src="js/dashboard.js"></script>