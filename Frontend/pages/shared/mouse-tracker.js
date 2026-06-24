(() => {
    const existing = document.querySelector('.mouse-tracker');
    if (existing) {
        return;
    }

    const tracker = document.createElement('div');
    tracker.className = 'mouse-tracker';
    tracker.setAttribute('aria-hidden', 'true');
    tracker.innerHTML = '<div class="mouse-tracker__core"></div>';
    document.body.appendChild(tracker);

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const coarsePointer = window.matchMedia('(pointer: coarse)');

    if (reducedMotion.matches || coarsePointer.matches) {
        tracker.style.display = 'none';
        return;
    }

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let currentX = mouseX;
    let currentY = mouseY;
    let frame = null;

    const render = () => {
        currentX += (mouseX - currentX) * 0.12;
        currentY += (mouseY - currentY) * 0.12;
        tracker.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;

        if (Math.abs(mouseX - currentX) < 0.1 && Math.abs(mouseY - currentY) < 0.1) {
            frame = null;
            return;
        }

        frame = window.requestAnimationFrame(render);
    };

    const schedule = () => {
        if (frame === null) {
            frame = window.requestAnimationFrame(render);
        }
    };

    window.addEventListener('mousemove', (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;
        schedule();
    }, { passive: true });

    window.addEventListener('mouseleave', () => {
        tracker.classList.add('mouse-tracker--idle');
    });

    window.addEventListener('mouseenter', () => {
        tracker.classList.remove('mouse-tracker--idle');
    });

    schedule();
})();