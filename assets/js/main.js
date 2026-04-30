/**
 * Orphanage Management System - Main JavaScript
 * HopeNest
 */

document.addEventListener('DOMContentLoaded', function () {

    // =========================================
    // Navigation Toggle (Mobile)
    // =========================================
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });

        // Close menu on link click
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            });
        });
    }

    // =========================================
    // Navbar Scroll Effect
    // =========================================
    const navbar = document.getElementById('mainNavbar');
    if (navbar) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // =========================================
    // Auth Tabs (Login Page)
    // =========================================
    const authTabs = document.querySelectorAll('.auth-tab');
    const authSections = document.querySelectorAll('.auth-form-section');

    authTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;

            authTabs.forEach(t => t.classList.remove('active'));
            authSections.forEach(s => s.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(target)?.classList.add('active');
        });
    });

    // =========================================
    // Flash Message Auto-dismiss
    // =========================================
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateX(100px)';
            setTimeout(() => flashMessage.remove(), 400);
        }, 5000);
    }

    // =========================================
    // Donation Amount Selection
    // =========================================
    const amountOptions = document.querySelectorAll('.amount-option');
    const amountInput = document.getElementById('donationAmount');

    amountOptions.forEach(option => {
        option.addEventListener('click', function () {
            amountOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            if (amountInput) {
                amountInput.value = this.dataset.amount;
            }
        });
    });

    // =========================================
    // Form Validation
    // =========================================
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            let isValid = true;
            const required = form.querySelectorAll('[required]');

            required.forEach(field => {
                removeError(field);
                if (!field.value.trim()) {
                    showError(field, 'This field is required');
                    isValid = false;
                }
            });

            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    showError(field, 'Please enter a valid email address');
                    isValid = false;
                }
            });

            // Password confirmation
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                showError(confirmPassword, 'Passwords do not match');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    function showError(field, message) {
        field.style.borderColor = '#e17055';
        let errorDiv = field.parentElement.querySelector('.form-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            field.parentElement.appendChild(errorDiv);
        }
        errorDiv.textContent = message;
    }

    function removeError(field) {
        field.style.borderColor = '';
        const errorDiv = field.parentElement.querySelector('.form-error');
        if (errorDiv) errorDiv.remove();
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // =========================================
    // Confirm Delete
    // =========================================
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // =========================================
    // Modal Handling
    // =========================================
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', function () {
            const modal = document.getElementById(this.dataset.modal);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', function (e) {
            if (e.target === this) {
                this.closest('.modal-overlay')?.classList.remove('active');
            }
        });
    });

    // =========================================
    // Search Filter
    // =========================================
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            const items = document.querySelectorAll('[data-searchable]');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // =========================================
    // Animate Score Bars
    // =========================================
    const scoreBars = document.querySelectorAll('.score-bar-fill');
    if (scoreBars.length > 0) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bar = entry.target;
                    bar.style.width = bar.dataset.width || '0%';
                }
            });
        }, { threshold: 0.3 });

        scoreBars.forEach(bar => {
            bar.style.width = '0%';
            observer.observe(bar);
        });
    }

    // =========================================
    // Counter Animation (Hero Stats)
    // =========================================
    const counters = document.querySelectorAll('[data-count]');
    if (counters.length > 0) {
        const counterObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => counterObserver.observe(counter));
    }

    function animateCounter(element) {
        const target = parseInt(element.dataset.count);
        const suffix = element.dataset.suffix || '';
        const duration = 2000;
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * eased);

            element.textContent = current + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }
});
