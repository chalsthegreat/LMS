  let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-item');
        const totalSlides = slides.length;

        // Initialize indicators
        function initIndicators() {
            const indicatorsContainer = document.getElementById('indicators');
            for (let i = 0; i < totalSlides; i++) {
                const indicator = document.createElement('div');
                indicator.className = 'indicator' + (i === 0 ? ' active' : '');
                indicator.onclick = () => goToSlide(i);
                indicatorsContainer.appendChild(indicator);
            }
        }

        function showSlide(n) {
            slides.forEach(slide => slide.classList.remove('active'));
            document.querySelectorAll('.indicator').forEach(ind => ind.classList.remove('active'));
            
            slides[n].classList.add('active');
            document.querySelectorAll('.indicator')[n].classList.add('active');
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        function prevSlide() {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            showSlide(currentSlide);
        }

        function goToSlide(n) {
            currentSlide = n;
            showSlide(currentSlide);
        }

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        const bodyElement = document.body;

        // Check for saved theme preference or default to dark mode
        const currentTheme = localStorage.getItem('theme') || 'dark';
        if (currentTheme === 'light') {
            bodyElement.classList.add('light-mode');
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }

        themeToggle.addEventListener('click', () => {
            bodyElement.classList.toggle('light-mode');
            const theme = bodyElement.classList.contains('light-mode') ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            themeToggle.innerHTML = theme === 'light' ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
        });

        // Initialize
        initIndicators();

        // Auto-slide carousel every 5 seconds
        let autoSlideInterval = setInterval(nextSlide, 5000);

        // Reset interval on manual navigation
        document.querySelectorAll('.carousel-btn, .indicator').forEach(btn => {
            btn.addEventListener('click', () => {
                clearInterval(autoSlideInterval);
                autoSlideInterval = setInterval(nextSlide, 5000);
            });
        });