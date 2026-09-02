(function () {
  'use strict';

  document.querySelectorAll('.video-facade[data-video-id]').forEach(function (facade) {
    facade.addEventListener('click', function () {
      var videoId = facade.getAttribute('data-video-id') || '';
      if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) return;

      var player = document.createElement('div');
      player.className = 'video-facade';
      var iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(videoId) + '?autoplay=1&rel=0';
      iframe.title = facade.getAttribute('data-video-title') || 'YouTube-video';
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      iframe.allowFullscreen = true;
      player.appendChild(iframe);
      facade.replaceWith(player);
    }, { once: true });
  });

  var search = document.getElementById('episode-search');
  var grid = document.getElementById('episode-grid');
  if (!search || !grid) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.episode-card-v2'));
  var filters = Array.prototype.slice.call(document.querySelectorAll('.filter-btn[data-topic]'));
  var resultCount = document.getElementById('result-count');
  var emptyState = document.getElementById('empty-state');
  var activeTopic = 'all';

  function normalized(value) {
    return (value || '').toLocaleLowerCase('da-DK').trim();
  }

  function updateEpisodes() {
    var query = normalized(search.value);
    var visible = 0;

    cards.forEach(function (card) {
      var text = normalized(card.getAttribute('data-search'));
      var topics = normalized(card.getAttribute('data-topics')).split('|');
      var matchesSearch = !query || text.indexOf(query) !== -1;
      var matchesTopic = activeTopic === 'all' || topics.indexOf(activeTopic) !== -1;
      card.hidden = !(matchesSearch && matchesTopic);
      if (!card.hidden) visible += 1;
    });

    if (resultCount) resultCount.textContent = visible + (visible === 1 ? ' episode' : ' episoder');
    if (emptyState) emptyState.style.display = visible ? 'none' : 'block';
  }

  search.addEventListener('input', updateEpisodes);
  filters.forEach(function (button) {
    button.addEventListener('click', function () {
      activeTopic = normalized(button.getAttribute('data-topic'));
      filters.forEach(function (item) {
        item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
      });
      updateEpisodes();
    });
  });
}());
