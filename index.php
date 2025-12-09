<?php
/**
 * NileTech Learning Website - Homepage
 * Unified homepage that handles both logged-in and non-logged-in users
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$currentUserName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
$isAdmin = ($userRole === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NileTech Learning Website - Digital Skills Training Platform</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php" style="text-decoration: none; color: inherit;">
                    <h1>NileTech</h1>
                </a>
            </div>
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="#home">Home</a></li>
                <li><a href="#modules">Modules</a></li>
                <?php if ($isLoggedIn): ?>
                    <li><a href="mentorship.php">Mentorship</a></li>
                    <li><a href="feedback.php">Feedback</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <?php if ($isAdmin): ?>
                        <li><a href="admin/index.php" class="btn btn-primary btn-nav" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">Admin Panel</a></li>
                    <?php endif; ?>
                    <!-- Notifications Bell -->
                    <li style="position: relative;">
                        <a href="#" id="notification-bell" class="nav-link-login" style="position: relative; padding: 8px 12px; text-decoration: none;">
                            🔔
                            <span id="notification-badge" style="display: none; position: absolute; top: 0; right: 0; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">0</span>
                        </a>
                        <div id="notification-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; width: 350px; max-height: 500px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 10px;">
                            <div style="padding: 15px; text-align: center; color: #666;">Loading notifications...</div>
                        </div>
                    </li>
                    <li class="nav-user">
                        <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <li><a href="logout.php" class="nav-link-login">Logout</a></li>
                <?php else: ?>
                    <li><a href="#mentorship">Mentorship</a></li>
                    <li><a href="#feedback">Feedback</a></li>
                    <li><a href="login.php" class="nav-link-login">Login</a></li>
                    <li><a href="signup.php" class="btn btn-primary btn-nav">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Animated Water Background -->
    <div class="water-background">
        <div class="water-layer water-layer-1"></div>
        <div class="water-layer water-layer-2"></div>
        <div class="water-layer water-layer-3"></div>
        <div class="water-droplets"></div>
    </div>

    <!-- Home / About Section -->
    <section id="home" class="hero">
        <!-- Floating Water Elements -->
        <div class="water-bubbles">
            <div class="bubble bubble-1"></div>
            <div class="bubble bubble-2"></div>
            <div class="bubble bubble-3"></div>
            <div class="bubble bubble-4"></div>
            <div class="bubble bubble-5"></div>
        </div>
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">NileTech Learning Website</h1>
                <p class="hero-tagline">"Flowing like the Nile, growing like the savanna - Making technology learning simple, practical, and accessible for all."</p>
                <p class="hero-description">
                    NileTech is a youth-led digital skills training platform dedicated to inspiring and empowering 
                    the youth of South Sudan through accessible technology education. Just as the Nile River flows 
                    through our land, bringing life and opportunity, we flow knowledge and skills to every learner. 
                    We believe that everyone deserves the opportunity to learn, grow, and succeed in the digital age. 
                    Join us in building a future where technology knowledge is within everyone's reach across the 
                    beautiful lands of South Sudan.
                </p>
                <?php if ($isLoggedIn): ?>
                    <p class="hero-description">
                        You are logged in as 
                        <strong><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></strong>. 
                        Choose a module below to continue your learning journey.
                    </p>
                <?php endif; ?>
                <div class="hero-buttons">
                    <?php if ($isLoggedIn): ?>
                        <a href="#modules" class="btn btn-primary">View Modules</a>
                        <a href="mentorship.php" class="btn btn-secondary btn-outline">Find a Mentor</a>
                    <?php else: ?>
                        <a href="signup.php" class="btn btn-primary" id="getStartedBtn">Get Started</a>
                        <a href="login.php" class="btn btn-secondary btn-outline">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Student Modules Section -->
    <section id="modules" class="modules">
        <div class="container">
            <h2 class="section-title">Student Modules</h2>
            <p class="section-subtitle">Choose a module to start your learning journey</p>
            
            <!-- Module Search Bar -->
            <div class="search-container" style="max-width: 600px; margin: 30px auto;">
                <div class="search-box" style="position: relative;">
                    <input type="text" 
                           id="module-search-input" 
                           class="search-input" 
                           placeholder="Search modules by name or description..."
                           style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;">
                    <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666;">🔍</span>
                    <div id="module-search-results" 
                         class="search-results" 
                         data-logged-in="<?php echo $isLoggedIn ? 'true' : 'false'; ?>"
                         style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; margin-top: 5px; max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
                </div>
            </div>
            
            <div class="modules-grid">
                <!-- Computer Literacy Module -->
                <div class="module-card">
                    <div class="module-icon">💻</div>
                    <h3 class="module-title">Computer Literacy</h3>
                    <p class="module-description">
                        Master the fundamentals of computer usage, from basic operations to file management 
                        and internet navigation. Build confidence in using technology for everyday tasks.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="module.php?module=computer-literacy" class="btn btn-module" data-module="computer-literacy">Start Module</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-module" data-module="computer-literacy">Login to Start</a>
                    <?php endif; ?>
                </div>

                <!-- CV Writing Module -->
                <div class="module-card">
                    <div class="module-icon">📝</div>
                    <h3 class="module-title">CV Writing</h3>
                    <p class="module-description">
                        Learn how to create professional CVs and resumes that stand out. Discover the secrets 
                        of effective formatting, content organization, and presentation that employers value.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="module.php?module=cv-writing" class="btn btn-module" data-module="cv-writing">Start Module</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-module" data-module="cv-writing">Login to Start</a>
                    <?php endif; ?>
                </div>

                <!-- Coding Module -->
                <div class="module-card">
                    <div class="module-icon">💻</div>
                    <h3 class="module-title">Coding</h3>
                    <p class="module-description">
                        Learn coding fundamentals and build your first projects. Develop skills that open doors to exciting career opportunities.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="module.php?module=coding" class="btn btn-module" data-module="coding">Start Module</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-module" data-module="coding">Login to Start</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Mentorship Section -->
    <section id="mentorship" class="mentorship">
        <div class="container">
            <div class="mentorship-content">
                <div class="mentorship-text">
                    <h2 class="section-title">Mentorship</h2>
                    <p class="mentorship-description">
                        Connect with peers and role models for guidance and support. Our mentorship program 
                        connects you with experienced professionals and fellow learners who can help you navigate 
                        your learning journey, answer questions, and provide valuable insights and encouragement.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="mentorship.php" class="btn btn-secondary" id="exploreMentors">Explore Mentors</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" id="exploreMentors">Explore Mentors</button>
                    <?php endif; ?>
                </div>
                <div class="mentorship-image">
                    <div class="placeholder-image">🤝</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feedback Section -->
    <section id="feedback" class="feedback">
        <div class="container">
            <div class="feedback-content">
                <div class="feedback-image">
                    <div class="placeholder-image">💬</div>
                </div>
                <div class="feedback-text">
                    <h2 class="section-title">Feedback</h2>
                    <p class="feedback-description">
                        Share your experiences, ideas, and success stories. Your feedback helps us improve 
                        our platform and inspires others in the community. We value your voice and want to 
                        hear about your learning journey, challenges, and achievements.
                    </p>
                    <?php if ($isLoggedIn): ?>
                        <a href="feedback.php" class="btn btn-secondary" id="submitFeedback">Submit Feedback</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" id="submitFeedback">Submit Feedback</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/search.js"></script>
    <script src="js/notifications.js"></script>
    <style>
        /* Water Background Layers */
        .water-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .water-layer {
            position: absolute;
            width: 200%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 77, 115, 0.1) 0%, rgba(0, 105, 148, 0.15) 50%, rgba(0, 139, 139, 0.1) 100%);
            opacity: 0.6;
        }

        .water-layer-1 {
            bottom: 0;
            left: -50%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 200" preserveAspectRatio="none"><path d="M0,100 Q300,50 600,100 T1200,100 L1200,200 L0,200 Z" fill="rgba(0,105,148,0.1)"/></svg>') repeat-x;
            background-size: 1200px 200px;
            animation: waterFlow1 15s linear infinite;
        }

        .water-layer-2 {
            bottom: 20%;
            left: -100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 180" preserveAspectRatio="none"><path d="M0,120 Q250,60 500,120 T1000,120 Q1250,180 1200,120 L1200,180 L0,180 Z" fill="rgba(0,139,139,0.08)"/></svg>') repeat-x;
            background-size: 1200px 180px;
            animation: waterFlow2 20s linear infinite;
        }

        .water-layer-3 {
            bottom: 40%;
            left: -150%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 160" preserveAspectRatio="none"><path d="M0,100 Q200,40 400,100 T800,100 Q1000,160 1200,100 L1200,160 L0,160 Z" fill="rgba(0,179,179,0.06)"/></svg>') repeat-x;
            background-size: 1200px 160px;
            animation: waterFlow3 25s linear infinite;
        }

        @keyframes waterFlow1 {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50%) translateY(-5px); }
        }

        @keyframes waterFlow2 {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50%) translateY(3px); }
        }

        @keyframes waterFlow3 {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50%) translateY(-3px); }
        }

        /* Water Droplets */
        .water-droplets {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .water-droplets::before,
        .water-droplets::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(0, 179, 179, 0.4);
            border-radius: 50%;
            animation: dropletFall 8s linear infinite;
        }

        .water-droplets::before {
            left: 10%;
            animation-delay: 0s;
        }

        .water-droplets::after {
            left: 85%;
            animation-delay: 4s;
        }

        @keyframes dropletFall {
            0% {
                top: -10px;
                opacity: 1;
                transform: translateY(0);
            }
            100% {
                top: 100%;
                opacity: 0;
                transform: translateY(100vh);
            }
        }

        /* Floating Water Bubbles */
        .water-bubbles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            overflow: hidden;
            z-index: 0;
        }

        .bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(2px);
            animation: bubbleFloat 15s infinite ease-in-out;
        }

        .bubble-1 {
            width: 80px;
            height: 80px;
            left: 10%;
            animation-delay: 0s;
            animation-duration: 20s;
        }

        .bubble-2 {
            width: 60px;
            height: 60px;
            left: 30%;
            animation-delay: 2s;
            animation-duration: 18s;
        }

        .bubble-3 {
            width: 100px;
            height: 100px;
            left: 60%;
            animation-delay: 4s;
            animation-duration: 22s;
        }

        .bubble-4 {
            width: 50px;
            height: 50px;
            left: 80%;
            animation-delay: 1s;
            animation-duration: 16s;
        }

        .bubble-5 {
            width: 70px;
            height: 70px;
            left: 50%;
            animation-delay: 3s;
            animation-duration: 19s;
        }

        @keyframes bubbleFloat {
            0% {
                bottom: -100px;
                transform: translateX(0) scale(1);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            50% {
                transform: translateX(50px) scale(1.2);
                opacity: 0.8;
            }
            100% {
                bottom: 100%;
                transform: translateX(-30px) scale(0.8);
                opacity: 0;
            }
        }

        /* Enhanced Hero Section with Water Effects */
        .hero {
            position: relative;
            overflow: hidden;
        }

        /* Water Ripple Effect on Hero */
        .hero::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 300px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 300" preserveAspectRatio="none"><path d="M0,150 C300,100 600,200 900,150 C1050,125 1125,175 1200,150 L1200,300 L0,300 Z" fill="rgba(255,255,255,0.15)"/><path d="M0,180 C250,130 500,230 750,180 C900,155 975,205 1200,180 L1200,300 L0,300 Z" fill="rgba(255,255,255,0.1)"/></svg>') repeat-x;
            background-size: 1200px 300px;
            animation: waterRipple 18s linear infinite;
            opacity: 0.7;
            z-index: 0;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: 50px;
            left: 0;
            right: 0;
            width: 100%;
            height: 250px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 250" preserveAspectRatio="none"><path d="M0,140 C200,90 400,190 600,140 C800,90 1000,190 1200,140 L1200,250 L0,250 Z" fill="rgba(255,255,255,0.12)"/></svg>') repeat-x;
            background-size: 1200px 250px;
            animation: waterRipple 22s linear infinite reverse;
            opacity: 0.6;
            z-index: 0;
        }

        @keyframes waterRipple {
            0% {
                background-position-x: 0;
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
            100% {
                background-position-x: 1200px;
                transform: translateY(0);
            }
        }

        /* Ensure content is above water effects */
        .hero-content {
            position: relative;
            z-index: 10;
        }

        /* Add water flow animation to modules section */
        .modules {
            position: relative;
            overflow: hidden;
        }

        .modules::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 105, 148, 0.05), transparent);
            animation: waterFlowAcross 10s linear infinite;
        }

        @keyframes waterFlowAcross {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }

        /* Floating animation for module cards */
        .module-card {
            animation: gentleFloat 6s ease-in-out infinite;
        }

        .module-card:nth-child(2) {
            animation-delay: 2s;
        }

        .module-card:nth-child(3) {
            animation-delay: 4s;
        }

        @keyframes gentleFloat {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        /* Water shimmer effect on buttons */
        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: waterShimmer 3s infinite;
        }

        @keyframes waterShimmer {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }

        /* Ripple effect on button click */
        .btn {
            position: relative;
            overflow: hidden;
        }

        .btn:active::before {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            animation: ripple 0.6s ease-out;
        }

        @keyframes ripple {
            to {
                transform: translate(-50%, -50%) scale(4);
                opacity: 0;
            }
        }
    </style>
</body>
</html>

