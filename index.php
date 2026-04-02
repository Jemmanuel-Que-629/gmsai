<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Meadows Security Agency, Inc. | Excellence in Protection</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <style>
        :root {
            --gms-green: #2d7d32;
            --gms-light-green: #4caf50;
            --gms-orange: #f57c00; /* Accents from the logo ribbons */
            --gms-dark: #1b5e20;
            --gms-bg: #f9fbf9;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gms-bg);
            color: #333;
            scroll-behavior: smooth;
        }

        /* --- Navbar Styles --- */
        .navbar {
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 0.8rem 2rem;
        }

        .navbar-brand img {
            height: 55px;
        }

        .nav-link {
            font-weight: 500;
            color: #444 !important;
            margin: 0 15px;
            position: relative;
            transition: color 0.3s ease;
        }

        /* Hover Animation: Sliding Underline */
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: var(--gms-light-green);
            transition: width 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link:hover {
            color: var(--gms-green) !important;
        }

        .btn-auth-login {
            color: var(--gms-green);
            font-weight: 600;
            border: 2px solid transparent;
        }

        .btn-auth-register {
            background-color: var(--gms-green);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 24px;
            box-shadow: 0 4px 10px rgba(45, 125, 50, 0.2);
        }

        .btn-auth-register:hover {
            background-color: var(--gms-dark);
            color: white;
            transform: translateY(-2px);
        }

        /* --- Hero Section --- */
        .hero {
            position: relative;
            padding: 160px 0;
            background: linear-gradient(rgba(20, 50, 20, 0.8), rgba(20, 50, 20, 0.8)), 
                        url('images/guards.png') no-repeat;
            background-size: cover;
            background-position: center;
            color: white;
            clip-path: ellipse(150% 100% at 50% 0%);
        }

        /* --- Service Cards --- */
        .service-icon {
            font-size: 40px;
            color: var(--gms-green);
            margin-bottom: 20px;
            display: inline-block;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 50%;
        }

        .card-custom {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            height: 100%;
        }

        .card-custom:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        /* --- Application Section --- */
        #application {
            background-color: #fff;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
        }

        .app-sidebar {
            background-color: var(--gms-green);
            color: white;
            padding: 40px;
        }

        /* --- Footer --- */
        footer {
            background: #111;
            color: white; /* Changed from #aaa to white */
            padding: 80px 0 30px;
        }

        .footer-title {
            color: white;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .contact-item span {
            margin-right: 12px;
            color: var(--gms-light-green);
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--gms-orange);
            margin: 15px auto;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#home">
                <img src="images/logo.jpg" alt="GMSAI Logo" style="height: 50px; border-radius: 50%;">
                <div class="ms-2 lh-1 d-none d-md-block">
                    <span class="d-block fw-bold fs-5 text-success">GREEN MEADOWS</span>
                    <small class="text-muted" style="font-size: 10px;">SECURITY AGENCY, INC.</small>
                </div>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="material-symbols-outlined">menu</span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#application">Careers</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="login.php" class="btn btn-auth-register">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <section id="home" class="hero text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <h5 class="text-uppercase fw-bold mb-3" style="letter-spacing: 3px; color: var(--gms-light-green);">Established 1992</h5>
                    <h1 class="display-3 fw-bold mb-4">"Three Decades Strong: Your Security, Our Commitment"</h1>
                    <p class="lead mb-5 opacity-75">For over 32 years, Green Meadows has been the premier choice for professional security, offering reliable solutions backed by unparalleled expertise.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="#services" class="btn btn-auth-register btn-lg px-5 py-3">Explore Our Services</a>
                        <a href="#application" class="btn btn-outline-light btn-lg px-5 py-3">Join the Force</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold section-title">What We Offer</h2>
                <p class="text-muted">Tailored security programs for every environment.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card card-custom p-4 text-center">
                        <span class="material-symbols-outlined service-icon">apartment</span>
                        <h5 class="fw-bold">Campus Security</h5>
                        <p class="small text-muted">Specialized safety protocols for educational and industrial campuses.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-custom p-4 text-center">
                        <span class="material-symbols-outlined service-icon">analytics</span>
                        <h5 class="fw-bold">Vulnerability Assessment</h5>
                        <p class="small text-muted">Detailed risk mapping and strategic planning to prevent threats.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-custom p-4 text-center">
                        <span class="material-symbols-outlined service-icon">policy</span>
                        <h5 class="fw-bold">Security Consultancy</h5>
                        <p class="small text-muted">Expert advice on system audits and operational efficiency.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card card-custom p-4 text-center">
                        <span class="material-symbols-outlined service-icon">military_tech</span>
                        <h5 class="fw-bold">Elite Force</h5>
                        <p class="small text-muted">Uniformed and plain-clothes officers trained for rapid response.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="application" class="container my-5">
        <div class="row g-0 overflow-hidden shadow-lg rounded-4">
            <div class="col-lg-4 app-sidebar d-flex flex-column justify-content-center">
                <h2 class="fw-bold mb-4">Join GMSAI</h2>
                <p>We are always looking for dedicated, disciplined, and professional individuals to join our growing security force.</p>
                <ul class="list-unstyled mt-4">
                    <li class="mb-2"><span class="material-symbols-outlined align-middle me-2">check_circle</span> Competitive Salary</li>
                    <li class="mb-2"><span class="material-symbols-outlined align-middle me-2">check_circle</span> Insurance Benefits</li>
                    <li class="mb-2"><span class="material-symbols-outlined align-middle me-2">check_circle</span> Advanced Training</li>
                </ul>
            </div>
            <div class="col-lg-8 bg-white p-5">
                <h4 class="fw-bold mb-4">Employment Application</h4>
                <form>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Full Name</label>
                            <input type="text" class="form-control" placeholder="Juan Dela Cruz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Position Applied For</label>
                            <select class="form-select">
                                <option>Security Guard</option>
                                <option>Security Officer</option>
                                <option>Office Staff</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" class="form-control" placeholder="name@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="tel" class="form-control" placeholder="0917-000-0000">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Upload Resume / CV (PDF)</label>
                            <input type="file" class="form-control">
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-auth-register w-100 py-3">Submit Application</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <footer id="contact">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <img src="images/logo.jpg" alt="Logo" class="mb-4" style="height: 60px; border-radius: 50%">
                    <p class="small">Green Meadows Security Agency, Inc. (GMSAI) has been providing top-notch security solutions for over three decades. Your security is our commitment.</p>
                </div>
                <div class="col-md-4 col-lg-2">
                    <h6 class="footer-title">Navigation</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="#home" class="text-decoration-none" style="color:white">Home</a></li>
                        <li class="mb-2"><a href="#services" class="text-decoration-none" style="color:white">Services</a></li>
                        <li class="mb-2"><a href="#application" class="text-decoration-none" style="color:white">Careers</a></li>
                    </ul>
                </div>
                <div class="col-md-8 col-lg-6">
                    <h6 class="footer-title">Connect With Us</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="contact-item small">
                                <span class="material-symbols-outlined">call</span>
                                +63 49 523-8420
                            </div>
                            <div class="contact-item small">
                                <span class="material-symbols-outlined">mail</span>
                                reynentorres.gmsai@gmail.com
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="contact-item small text-wrap">
                                <span class="material-symbols-outlined">location_on</span>
                                Calamba City, Laguna, Philippines
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="border-secondary mt-5">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="small mb-0">&copy; 2026 Green Meadows Security Agency, Inc.</p>
                </div>
                <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                    <span class="text-white small">Developed by JC and Beavs</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Smooth scrolling with offset for navbar
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    const navHeight = document.querySelector('.navbar').offsetHeight;
                    window.scrollTo({
                        top: targetElement.offsetTop - navHeight,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Navbar appearance on scroll
        window.onscroll = function() {
            const nav = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                nav.style.padding = "0.5rem 2rem";
                nav.style.background = "rgba(255, 255, 255, 0.95)";
            } else {
                nav.style.padding = "0.8rem 2rem";
                nav.style.background = "#ffffff";
            }
        };
    </script>
</body>
</html>