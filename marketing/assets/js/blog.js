// Blog index: filter the posts by topic. Without JavaScript every post simply shows.
(function () {
  var bar = document.querySelector('.blog-filter');
  if (!bar) return;
  var cards = document.querySelectorAll('#posts .post-card');
  bar.hidden = false;
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-cat]');
    if (!b) return;
    var cat = b.getAttribute('data-cat');
    bar.querySelectorAll('button').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    cards.forEach(function (c) { c.hidden = !!cat && c.getAttribute('data-cat') !== cat; });
  });
})();
