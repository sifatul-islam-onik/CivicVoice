<?php
require_once 'config.php';
require_once 'includes/auth_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectToDashboard();
}

$page_title = "CivicVoice - Community Issue Reporting Platform";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-bullhorn"></i>
                <span>CivicVoice</span>
            </div>
            <div class="nav-menu">
                <a href="#features" class="nav-link">Features</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="#about" class="nav-link">About</a>
                <a href="login.php" class="nav-link btn-outline">Login</a>
                <a href="register.php" class="nav-link btn-primary">Get Started</a>
            </div>
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Your Voice Matters in Building Better Communities</h1>
                <p class="hero-subtitle">Report local issues, track their progress, and work together with authorities to create positive change in your neighborhood.</p>
                <div class="hero-buttons">
                    <a href="register.php" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i>
                        Join CivicVoice
                    </a>
                    <a href="#how-it-works" class="btn btn-secondary btn-large">
                        <i class="fas fa-play"></i>
                        See How It Works
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-graphic">
                    <i class="fas fa-city"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2>Empowering Communities Through Technology</h2>
                <p>CivicVoice provides the tools citizens and authorities need to collaborate effectively</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>Easy Reporting</h3>
                    <p>Report issues quickly with photos, location data, and detailed descriptions. Our intuitive interface makes civic engagement simple.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Location Tracking</h3>
                    <p>Automatic geolocation ensures authorities can find and address issues precisely where they occur in your community.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Real-time Updates</h3>
                    <p>Stay informed about the progress of your reports and community issues with instant notifications and status updates.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Community Collaboration</h3>
                    <p>Connect with neighbors, support important issues, and work together to create meaningful change in your area.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Authority Dashboard</h3>
                    <p>Local authorities get dedicated tools to manage, prioritize, and resolve community issues efficiently.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Progress Analytics</h3>
                    <p>Track community improvement trends and measure the impact of civic engagement over time.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Simple Steps to Make a Difference</h2>
                <p>Getting started with CivicVoice is easy and takes just minutes</p>
            </div>
            
            <div class="steps-container">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Create Your Account</h3>
                        <p>Sign up as a citizen in seconds. Your username is automatically generated from your name for convenience.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Report an Issue</h3>
                        <p>Take a photo, add your location, and describe the problem. Our form guides you through everything needed.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Track Progress</h3>
                        <p>Watch as local authorities review, prioritize, and work to resolve your report. Get updates every step of the way.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>See Results</h3>
                        <p>Celebrate improvements in your community and continue reporting to make your neighborhood even better.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">1,247</div>
                    <div class="stat-label">Issues Reported</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">892</div>
                    <div class="stat-label">Issues Resolved</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">156</div>
                    <div class="stat-label">Active Citizens</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">12</div>
                    <div class="stat-label">Partner Authorities</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>Building Stronger Communities Together</h2>
                    <p>CivicVoice was created with a simple mission: to bridge the gap between citizens and local authorities through technology. We believe that when communities have the right tools to communicate and collaborate, amazing things happen.</p>
                    
                    <p>Our platform empowers every citizen to be an active participant in improving their neighborhood, while giving authorities the insights and tools they need to respond effectively to community needs.</p>
                    
                    <div class="about-features">
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Transparent reporting process</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Real-time communication</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Data-driven insights</span>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-check-circle"></i>
                            <span>Community-focused design</span>
                        </div>
                    </div>
                </div>
                
                <div class="about-image">
                    <div class="about-graphic">
                        <i class="fas fa-handshake"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Make Your Voice Heard?</h2>
                <p>Join thousands of citizens who are already making a difference in their communities</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn btn-primary btn-large">
                        <i class="fas fa-rocket"></i>
                        Start Reporting Issues
                    </a>
                    <a href="login.php" class="btn btn-outline btn-large">
                        <i class="fas fa-sign-in-alt"></i>
                        Already Have Account?
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-bullhorn"></i>
                        <span>CivicVoice</span>
                    </div>
                    <p>Empowering communities through better communication and collaboration.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="register.php">Get Started</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Account</h3>
                    <ul>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="forgot_password.php">Forgot Password</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact</h3>
                    <ul>
                        <li><i class="fas fa-envelope"></i> hello@civicvoice.com</li>
                        <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt"></i> Your City, Your State</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> CivicVoice. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile navigation toggle
        document.querySelector('.nav-toggle').addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Header background on scroll
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Animate statistics on scroll
        function animateStats() {
            const stats = document.querySelectorAll('.stat-number');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = parseInt(entry.target.textContent.replace(/,/g, ''));
                        animateNumber(entry.target, target);
                        observer.unobserve(entry.target);
                    }
                });
            });

            stats.forEach(stat => observer.observe(stat));
        }

        function animateNumber(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 20);
        }

        // Initialize animations
        document.addEventListener('DOMContentLoaded', animateStats);
    </script>
</body>
</html>