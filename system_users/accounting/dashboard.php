<?php
include '../../global/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    #wrapper { overflow-x: hidden; }
    #page-content-wrapper { min-width: 100vw; }
    @media (min-width: 768px) {
        #page-content-wrapper { min-width: 0; width: 100%; }
    }
</style>

<div class="d-flex" id="wrapper">
    <?php include '../../global/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100">
        <div class="container-fluid px-4 py-4">
            <h4 class="mb-3 fw-bold">Accounting Dashboard</h4>
            <div class="card border-0 shadow-sm p-4">
                <p class="mb-0 text-muted">Welcome.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
