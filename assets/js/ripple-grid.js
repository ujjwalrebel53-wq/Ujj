/** IRIS-style hero ripple grid (matches irisaiw.vercel.app hero background) */
(function () {
  const canvas = document.getElementById('ripple-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let w, h, mx = 0.5, my = 0.5, t = 0;

  function resize() {
    w = canvas.width = canvas.offsetWidth;
    h = canvas.height = canvas.offsetHeight;
  }

  function draw() {
    t += 0.012;
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, w, h);
    const gx = 10, gy = 10;
    const cellW = w / gx, cellH = h / gy;
    for (let i = 0; i <= gx; i++) {
      for (let j = 0; j <= gy; j++) {
        const x = i * cellW, y = j * cellH;
        const dx = x / w - mx, dy = y / h - my;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const ripple = Math.sin(dist * 18 - t * 4) * 0.5 + 0.5;
        const a = 0.08 + ripple * 0.35 * Math.max(0, 1 - dist * 1.2);
        ctx.strokeStyle = `rgba(15, 130, 24, ${a})`;
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, h);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(w, y);
        ctx.stroke();
      }
    }
    requestAnimationFrame(draw);
  }

  window.addEventListener('mousemove', (e) => {
    const r = canvas.getBoundingClientRect();
    mx = (e.clientX - r.left) / r.width;
    my = (e.clientY - r.top) / r.height;
  });
  window.addEventListener('resize', resize);
  resize();
  draw();
})();
