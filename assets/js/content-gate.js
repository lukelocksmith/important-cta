(function () {
  'use strict';

  var gate = document.querySelector('.icta-gate');
  if (!gate) return;

  var postId = gate.getAttribute('data-post-id');
  var storageKey = 'icta_unlocked_' + postId;
  var gatedContent = document.querySelector('.icta-gated-content[data-post-id="' + postId + '"]');

  if (!gatedContent) return;

  // Check if already unlocked
  if (localStorage.getItem(storageKey) === '1' || localStorage.getItem('icta_unlocked_all') === '1') {
    unlock();
    return;
  }

  // Watch for Fluent Forms submission success
  // FF replaces form with success message — we detect this via MutationObserver
  var formContainer = gate.querySelector('.icta-gate__form');
  if (formContainer) {
    var observer = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (node.nodeType !== 1) continue;
          // Fluent Forms shows .ff-message-success after submission
          if (node.classList && (
            node.classList.contains('ff-message-success') ||
            node.querySelector && node.querySelector('.ff-message-success')
          )) {
            localStorage.setItem(storageKey, '1');
            // Fire custom event for analytics tracking
            document.dispatchEvent(new CustomEvent('icta:unlocked', { detail: { postId: postId } }));
            // Small delay for user to see success message
            setTimeout(unlock, 1500);
            observer.disconnect();
            return;
          }
        }
      }
    });

    observer.observe(formContainer, { childList: true, subtree: true });
  }

  // Also listen for jQuery-based FF events (fallback)
  if (typeof jQuery !== 'undefined') {
    jQuery(document).on('fluentform_submission_success', function () {
      localStorage.setItem(storageKey, '1');
      document.dispatchEvent(new CustomEvent('icta:unlocked', { detail: { postId: postId } }));
      setTimeout(unlock, 1500);
    });
  }

  function unlock() {
    gate.classList.add('icta-gate--unlocked');
    gatedContent.style.display = '';
    // Smooth reveal
    gatedContent.style.opacity = '0';
    gatedContent.style.transition = 'opacity 0.4s ease';
    requestAnimationFrame(function () {
      gatedContent.style.opacity = '1';
    });
  }
})();
