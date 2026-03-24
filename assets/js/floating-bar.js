(function () {
  'use strict';

  // ── Reading Progress Bar ──────────────────────
  var progressBar = document.getElementById('blm-progress-bar');
  if (progressBar) {
    function updateProgress() {
      var scrollTop = window.scrollY;
      var docHeight = document.documentElement.scrollHeight - window.innerHeight;
      var pct = docHeight > 0 ? Math.min(100, Math.round((scrollTop / docHeight) * 100)) : 0;
      progressBar.style.width = pct + '%';
    }
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
  }

  // ── Floating Bar TOC ──────────────────────────
  var bar = document.getElementById('blm-float');
  if (!bar) return;

  var content = document.getElementById('post-content');
  if (!content) return;

  var tocList   = document.getElementById('blm-toc-list');
  var tocActive = document.getElementById('blm-toc-active');

  var headings = content.querySelectorAll('h2, h3');

  if (tocList) {
    if (headings.length < 2) {
      // Not enough headings — hide TOC area
      var tocArea = bar.querySelector('.blm-float__toc-area');
      var sep = bar.querySelector('.blm-float__sep');
      if (tocArea) tocArea.style.display = 'none';
      if (sep) sep.style.display = 'none';
    } else {
      // Build drawer TOC from existing headings (which already have IDs from server-side PHP)
      headings.forEach(function (h) {
        if (!h.id) return; // Skip headings without IDs

        var li = document.createElement('li');
        if (h.tagName === 'H3') li.className = 'toc-sub';

        var a = document.createElement('a');
        a.href = '#' + h.id;
        a.textContent = h.textContent;
        li.appendChild(a);
        tocList.appendChild(li);
      });

      // Active heading highlight via IntersectionObserver
      var links = tocList.querySelectorAll('a');

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var id = entry.target.id;

          links.forEach(function (a) {
            a.classList.remove('active');
            if (a.getAttribute('href') === '#' + id) a.classList.add('active');
          });

          if (tocActive) {
            tocActive.textContent = entry.target.textContent.trim();
          }
        });
      }, { rootMargin: '-80px 0px -60% 0px', threshold: 0 });

      headings.forEach(function (h) {
        if (h.id) observer.observe(h);
      });

      // Close panel after clicking a TOC link
      tocList.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
          bar.setAttribute('aria-expanded', 'false');
        });
      });
    }
  }
}());

// Toggle function (global, called from onclick)
function toggleBlmFloat() {
  var bar = document.getElementById('blm-float');
  if (!bar) return;
  var isOpen = bar.getAttribute('aria-expanded') === 'true';
  bar.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
}
