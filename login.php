<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GMSAI Login | Security & Commitment</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <style>
        :root {
            --gms-green: #2d7d32;
            --gms-dark: #1b5e20;
            --gms-body-bg: #f5f6f5;
        }

        body, html {
            height: 100%;
            font-family: 'Poppins', sans-serif;
            background-color: var(--gms-body-bg);
            margin: 0;
        }

        /* Flex wrapper to handle vertical spacing on mobile */
        .page-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px 20px; /* Prevents touching top/bottom on Android */
        }

        /* --- Fixed Back Arrow --- */
        .back-to-home {
            position: fixed; /* Keeps it visible regardless of scroll or layout */
            top: 20px;
            left: 20px;
            color: #70757a;
            text-decoration: none;
            transition: color 0.3s;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            padding: 8px;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .back-to-home:hover {
            color: var(--gms-green);
        }

        /* --- Main Contained Card --- */
        .login-card-container {
            width: 100%;
            max-width: 1100px; /* Wider container as requested */
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            position: relative;
        }

        /* --- Split Panels --- */
        .panel-half {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Left Side: Green Logo Area */
        .left-panel {
            background-color: var(--gms-green);
            color: white;
            padding: 4rem;
            align-items: center;
            text-align: center;
        }

        .brand-logo-white {
            height: 160px;
            width: 160px;
            border-radius: 50%;
            background: white; 
            padding: 12px;
            margin-bottom: 25px;
            object-fit: contain;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        /* Right Side: White Form Area */
        .right-panel {
            padding: 4rem;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px; /* Slightly wider internal form */
            margin: 0 auto;
        }

        /* --- Floating Labels --- */
        .input-group-custom {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-group-custom input {
            width: 100%;
            padding: 16px 15px;
            border: 1.5px solid #dadce0;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
            background: transparent;
        }

        .input-group-custom input[type="password"],
        .input-group-custom input[type="text"].pw-input {
            padding-right: 45px;
        }

        .input-group-custom label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 0 5px;
            color: #70757a;
            pointer-events: none;
            transition: all 0.2s ease;
        }

        .input-group-custom input:focus,
        .input-group-custom input:not(:placeholder-shown) {
            border-color: var(--gms-green);
            border-width: 2px;
        }

        .input-group-custom input:focus + label,
        .input-group-custom input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 0.75rem;
            color: var(--gms-green);
            font-weight: 600;
        }

        .field-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #70757a;
            user-select: none;
        }

        /* --- Buttons --- */
        .btn-green-submit {
            background-color: var(--gms-green);
            color: white;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 1rem;
            transition: all 0.3s;
        }

        .btn-green-submit:hover {
            background-color: var(--gms-dark);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .credits-footer {
            text-align: center;
            margin-top: 3rem;
            font-size: 0.85rem;
            color: #aaa;
        }

        /* --- Responsive Styles --- */
        @media (max-width: 992px) {
            .left-panel { padding: 3rem; }
            .right-panel { padding: 3rem; }
        }

        @media (max-width: 768px) {
            .login-card-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .left-panel {
                padding: 3.5rem 2rem;
                border-bottom-left-radius: 0;
                /* Keeps the design consistent with your mobile screenshot */
                clip-path: ellipse(150% 100% at 50% 0%); 
            }
            
            .right-panel {
                padding: 3rem 2rem;
            }

            .brand-logo-white { height: 110px; width: 110px; }
            
            /* Ensure fixed arrow stays above the green panel on mobile */
            .back-to-home {
                background: white;
                box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-to-home">
        <span class="material-symbols-outlined">arrow_back</span>
    </a>

    <div class="page-wrapper">
        <div class="login-card-container">
            
            <div class="panel-half left-panel">
                <img src="images/logo.jpg" alt="GMSAI Logo" class="brand-logo-white">
                <h1 class="fw-bold mb-1">GMSAI</h1>
                <p class="text-center opacity-75">Green Meadows Security Agency, Inc.</p>
            </div>

            <div class="panel-half right-panel">
                <div class="login-wrapper">
                    <div class="mb-4">
                        <h2 class="fw-bold mb-1">Sign In</h2>
                        <p class="text-muted">Please enter your credentials</p>
                    </div>

                    <form action="login_process.php" method="POST">
                        <div class="input-group-custom">
                            <input type="email" name="email" id="email" placeholder=" " required autocomplete="email">
                            <label for="email">Email address</label>
                        </div>

                        <div class="input-group-custom">
                            <input type="password" name="password" id="password" class="pw-input" placeholder=" " required autocomplete="current-password">
                            <label for="password">Password</label>
                            <span class="material-symbols-outlined field-icon" id="togglePassword">visibility</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label small" for="remember">Remember me</label>
                            </div>
                            <a href="#" class="small text-decoration-none text-success fw-bold">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn-green-submit">Login</button>
                    </form>

                    <div class="credits-footer">
                        &copy; 2026 | Developed by JC and Beavs
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordField = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    </script>
</body>
</html>