// Pine Street Dental — fictional family practice. Shows the Business website: ten pages plus a blog, online booking.
import { head, demoBar, navLinks, menuBtn } from './common.mjs';

const NAV = [['services.html', 'Services'], ['new-patients.html', 'New patients'], ['team.html', 'Our team'], ['insurance.html', 'Insurance'], ['blog.html', 'Blog'], ['contact.html', 'Contact']];

const css = `
:root { --sage: #e7efe9; --mist: #f5f8f5; --pine: #16302b; --teal: #2a7363; --teal-2: #1f5c4f; --sand: #efe4d2; --muted: #4c615b; --line: rgba(22,48,43,.14); --display: "Newsreader", Georgia, serif; --body: "Manrope", system-ui, sans-serif; }
body { margin: 0; background: var(--mist); color: var(--pine); font: 17px/1.6 var(--body); }
.demo-bar { background: var(--pine); color: var(--sage); } .demo-bar a { color: #9fe0cf; font-weight: 700; }
.wrap { max-width: 1160px; margin: 0 auto; padding: 0 24px; } .narrow { max-width: 760px; }
header { display: flex; align-items: center; gap: 28px; height: 86px; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; font: 500 25px/1 var(--display); letter-spacing: -.01em; } .logo svg { width: 34px; height: 34px; }
.site-nav { display: flex; gap: 24px; margin-left: auto; font-weight: 500; font-size: 15.5px; } .site-nav a { text-decoration: none; color: var(--muted); } .site-nav a:hover, .site-nav a[aria-current] { color: var(--pine); }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 50px; padding: 0 24px; border-radius: 14px; background: var(--teal); color: #fff; font-weight: 600; text-decoration: none; border: 0; cursor: pointer; font-size: 16px; }
.btn:hover { background: var(--teal-2); } .btn.soft { background: var(--sage); color: var(--pine); } .btn.light { background: #fff; color: var(--pine); }
.hero { display: grid; grid-template-columns: 1.05fr .95fr; gap: 56px; align-items: center; padding: 40px 0 90px; }
.eyebrow { font-size: 14px; font-weight: 700; color: var(--teal); letter-spacing: .02em; }
h1 { font: 400 clamp(44px, 6.4vw, 84px)/1.02 var(--display); letter-spacing: -.025em; margin: 16px 0 22px; } h1 em { color: var(--teal); }
.page-hero { padding: 30px 0 60px; } .page-hero h1 { font-size: clamp(40px, 5.4vw, 68px); margin: 10px 0 16px; }
.lede { font-size: 19px; color: var(--muted); max-width: 560px; } .row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 30px; }
.facts { display: flex; gap: 28px; flex-wrap: wrap; margin-top: 36px; font-size: 14.5px; color: var(--muted); } .facts b { display: block; color: var(--pine); font: 500 24px/1.1 var(--display); }
.portrait { position: relative; aspect-ratio: 4 / 5; border-radius: 200px 200px 28px 28px; background: linear-gradient(170deg, #cfe2d8, #9cc6b6); overflow: hidden; }
.portrait .sun { position: absolute; width: 60%; aspect-ratio: 1; border-radius: 50%; background: #f7f1e4; left: 20%; top: 14%; }
.portrait .hill { position: absolute; left: -10%; right: -10%; bottom: -20%; height: 55%; border-radius: 50%; background: var(--teal); } .portrait .hill.b { background: var(--pine); left: 30%; bottom: -30%; }
.offer { position: absolute; left: -28px; bottom: 40px; background: #fff; border-radius: 20px; padding: 18px 20px; box-shadow: 0 20px 40px -20px rgba(22,48,43,.45); max-width: 240px; }
.offer b { display: block; font: 500 32px/1 var(--display); color: var(--teal); } .offer span { font-size: 14px; color: var(--muted); }
section { padding: 84px 0; } .tint { background: var(--sage); }
h2 { font: 400 clamp(34px, 4.4vw, 54px)/1.05 var(--display); letter-spacing: -.02em; margin: 0 0 14px; } .sub { color: var(--muted); max-width: 600px; margin: 0 0 40px; font-size: 18px; }
h3 { font: 500 26px/1.15 var(--display); margin: 0 0 8px; }
.grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; } .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start; }
.card { background: #fff; border-radius: 22px; padding: 28px; text-decoration: none; display: block; } a.card:hover { box-shadow: 0 16px 34px -22px rgba(22,48,43,.5); }
.card .ic { width: 46px; height: 46px; border-radius: 14px; background: var(--sage); display: grid; place-items: center; margin-bottom: 18px; }
.card p { margin: 0; color: var(--muted); font-size: 15.5px; } .card .more { display: inline-block; margin-top: 14px; color: var(--teal); font-weight: 700; font-size: 15px; }
.first { display: grid; grid-template-columns: repeat(4, 1fr); counter-reset: s; border-top: 1px solid var(--line); }
.first div { padding: 26px 22px 0 0; } .first div::before { counter-increment: s; content: counter(s); font: italic 400 42px/1 var(--display); color: var(--teal); }
.first b { display: block; margin: 14px 0 6px; font-size: 17px; } .first span { color: var(--muted); font-size: 15px; }
.doc { background: #fff; border-radius: 22px; overflow: hidden; } .doc .ph { aspect-ratio: 4 / 3; display: grid; place-items: center; font: 400 64px/1 var(--display); color: #fff; }
.doc .txt { padding: 20px 22px 24px; } .doc b { font: 500 22px/1.2 var(--display); display: block; } .doc span { color: var(--muted); font-size: 15px; } .doc p { color: var(--muted); font-size: 15px; margin: 10px 0 0; }
.list { padding: 0; margin: 0; list-style: none; } .list li { padding: 12px 0; border-bottom: 1px solid var(--line); display: flex; gap: 12px; } .list li::before { content: "•"; color: var(--teal); font-weight: 700; }
.cols2 { columns: 2; } .cols2 li { break-inside: avoid; }
.price { background: var(--pine); color: var(--sage); border-radius: 24px; padding: 34px; } .price b { font: 500 64px/1 var(--display); color: #fff; display: block; margin: 6px 0 10px; } .price p { margin: 0 0 20px; color: #b9d0c8; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 18px; overflow: hidden; } th, td { text-align: left; padding: 14px 18px; border-bottom: 1px solid var(--line); } th { background: var(--pine); color: #fff; font-weight: 600; font-size: 15px; }
.post { background: #fff; border-radius: 20px; padding: 26px; text-decoration: none; display: flex; flex-direction: column; gap: 8px; } .post small { color: var(--teal); font-weight: 700; } .post h3 { font-size: 24px; margin: 0; } .post p { margin: 0; color: var(--muted); font-size: 15px; } .post time { color: var(--muted); font-size: 14px; margin-top: auto; }
article.prose { font-size: 18.5px; } article.prose h2 { font-size: 34px; margin-top: 40px; } article.prose p, article.prose li { color: #2b443e; } article.prose .meta { color: var(--muted); font-size: 15px; }
.callout { background: var(--sage); border-radius: 18px; padding: 22px 24px; margin: 30px 0; }
form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: #fff; padding: 30px; border-radius: 24px; }
label { display: grid; gap: 6px; font-weight: 600; font-size: 14px; } input, select, textarea { font: inherit; padding: 13px 14px; border-radius: 12px; border: 1.5px solid #b9cbc2; background: var(--mist); color: var(--pine); }
fieldset { border: 0; padding: 0; margin: 0; } legend { font-weight: 600; font-size: 14px; margin-bottom: 8px; }
.slots { display: flex; flex-wrap: wrap; gap: 8px; } .slots label { display: block; } .slots input { position: absolute; opacity: 0; } .slots span { display: inline-block; padding: 10px 14px; border-radius: 10px; border: 1.5px solid #b9cbc2; font-weight: 600; cursor: pointer; } .slots input:checked + span { background: var(--teal); color: #fff; border-color: var(--teal); } .slots input:focus-visible + span { outline: 3px solid var(--pine); }
.full { grid-column: 1 / -1; } form .note { grid-column: 1 / -1; font-size: 14px; color: var(--teal); font-weight: 700; margin: 0; }
.hours { margin: 0; } .hours div { display: flex; justify-content: space-between; border-bottom: 1px solid var(--line); padding: 10px 0; } .hours dt { font-weight: 600; } .hours dd { margin: 0; color: var(--muted); }
.cta { background: var(--teal); color: #fff; border-radius: 28px; padding: 46px; display: flex; flex-wrap: wrap; gap: 24px; align-items: center; justify-content: space-between; } .cta h2 { margin: 0; } .cta p { margin: 6px 0 0; color: #d6ebe4; }
footer { background: var(--pine); color: #b9d0c8; padding: 56px 0 36px; font-size: 14.5px; }
.foot { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 28px; } .foot b { color: #fff; display: block; margin-bottom: 10px; font-family: var(--display); font-weight: 500; font-size: 19px; } .foot a { display: block; text-decoration: none; padding: 3px 0; } .foot a:hover { color: #fff; }
.foot-bottom { margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.12); display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; } .foot-bottom a { color: #9fe0cf; }
@media (max-width: 960px) { .site-nav { background: #fff; } .hero, .grid2 { grid-template-columns: 1fr; } .grid3 { grid-template-columns: 1fr; } .first, .foot { grid-template-columns: 1fr 1fr; } .portrait { max-width: 380px; } .offer { left: 12px; } .wrap { padding: 0 18px; } section { padding: 60px 0; } header > .btn { display: none; } }
@media (max-width: 560px) { form, .first, .foot { grid-template-columns: 1fr; } .cols2 { columns: 1; } .cta { padding: 30px 22px; } }
`;

const ic = d => `<span class="ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2a7363" stroke-width="1.8" aria-hidden="true">${d}</svg></span>`;
const IC = {
  tooth: '<path d="M7 3c-2.5 0-4 2-4 5 0 4 2 6 2.5 10 .3 2 1.2 3 2 3 1.5 0 1.5-5 4.5-5s3 5 4.5 5c.8 0 1.7-1 2-3C19 14 21 12 21 8c0-3-1.5-5-4-5-2 0-3 1-5 1s-3-1-5-1Z"/>',
  smile: '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/>',
  plus: '<path d="M12 3v18M3 12h18"/>', kid: '<circle cx="12" cy="7" r="4"/><path d="M5 21c0-4 3-7 7-7s7 3 7 7"/>',
  crown: '<path d="M3 8l4 4 5-7 5 7 4-4-2 11H5z"/>', calm: '<path d="M4 15c3-4 13-4 16 0"/><path d="M8 9h.01M16 9h.01"/>',
};

const SERVICES = [
  ['tooth', 'Checkups & cleanings', 'Exams, X-rays and gentle cleanings every six months. We explain what we see on the screen as we go.'],
  ['kid', 'Children’s dentistry', 'First visits from age one, sealants and fluoride, and a lot of patience. Parents can stay in the room.'],
  ['crown', 'Fillings & crowns', 'Tooth-colored fillings and same-day crowns made in the office, so you don’t need a second visit.'],
  ['smile', 'Cosmetic dentistry', 'Whitening, bonding and veneers, planned with you step by step.', 'cosmetic.html'],
  ['plus', 'Emergency care', 'Toothache, chipped tooth or lost filling? Call and we’ll see you today.', 'emergency.html'],
  ['calm', 'Comfort options', 'Noise-canceling headphones, warm blankets and nitrous oxide for nervous patients.'],
];

const POSTS = [
  ['first-dentist-visit', 'Kids', 'When should my child first see a dentist?', 'Sooner than most parents think. Here’s what a first visit looks like, and how to make it easy.', 'September 12', [
    ['By their first birthday', 'The American Academy of Pediatric Dentistry recommends a first visit when the first tooth appears, or by age one. It sounds early, but those first visits are short and mostly about getting comfortable.'],
    ['What happens at the visit', 'We count teeth, check the gums and bite, and talk with you about brushing, bottles and snacks. Many little ones sit on a parent’s lap the whole time.'],
    ['Making it easy', 'Book a morning slot when your child is rested, read a picture book about the dentist beforehand, and keep the words simple: “The dentist will count your teeth.”'],
  ]],
  ['cleaning-cost-without-insurance', 'Costs', 'What a cleaning costs without insurance, and how to save', 'Real numbers for our office, and three ways to pay less for routine care.', 'August 28', [
    ['What we charge', 'A new-patient exam, cleaning and X-rays is $99 at our office when you don’t have insurance. A routine cleaning and exam after that is $129.'],
    ['Our in-house savings plan', 'For $29 a month you get two cleanings and exams a year, X-rays, and 20% off other treatment. There’s no deductible and no waiting period.'],
    ['Use your HSA or FSA', 'Dental care counts as a qualified expense, so you can pay with pre-tax money if you have either account.'],
  ]],
  ['whitening-options', 'Whitening', 'Strips, trays or in-office: which whitening is worth it?', 'An honest comparison of cost, time and results, from people who do all three.', 'August 9', [
    ['Store-bought strips', 'Cheapest and fine for light staining. Results fade faster and the strips don’t reach every tooth evenly.'],
    ['Custom trays', 'We make trays that fit your teeth exactly and give you professional gel. Two weeks of wear at home, and you can touch up for years with the same trays.'],
    ['In-office whitening', 'The fastest: about 90 minutes in the chair. Best before a wedding or photos. Some people get short-term sensitivity, which we plan for.'],
  ]],
];

const bookForm = `<form data-demo-form="This is a demo, so no appointment was booked. On a live site the front desk gets the request and Site Stats counts a “Booking form sent” goal.">
  <label>Name<input name="name" autocomplete="name" required></label><label>Phone<input name="phone" type="tel" autocomplete="tel" required></label>
  <label>Email<input name="email" type="email" autocomplete="email"></label>
  <label>Visit type<select name="type"><option>New patient visit</option><option>Cleaning &amp; checkup</option><option>Toothache or emergency</option><option>Cosmetic consult</option><option>Child’s visit</option></select></label>
  <label>Preferred day<input name="day" type="date"></label>
  <fieldset><legend>Time of day</legend><div class="slots"><label><input type="radio" name="t" value="am" checked><span>Morning</span></label><label><input type="radio" name="t" value="pm"><span>Afternoon</span></label><label><input type="radio" name="t" value="eve"><span>Evening</span></label></div></fieldset>
  <label class="full">Insurance <small>(optional)</small><input name="ins" placeholder="Your plan name, or “none”"></label>
  <button class="btn full" type="submit">Request appointment</button>
  <p class="note" role="status"></p>
</form>`;

const cta = `<section><div class="wrap"><div class="cta"><div><h2>New patients welcome</h2><p>Same-week visits, evening hours on Tuesdays and Thursdays.</p></div><a class="btn light" href="book.html">Book online</a></div></div></section>`;
const faq = (items) => items.map(([q, a]) => `<details class="faq"><summary>${q}</summary><p>${a}</p></details>`).join('');

const page = (file, title, desc, nav, eyebrow, h1, lede, body) => ({ file, title, desc, nav, eyebrow, h1, lede, body });

const pages = [
  { file: 'index.html', title: 'Pine Street Dental · Family & cosmetic dentistry', desc: 'Gentle family and cosmetic dentistry with evening hours and online booking.', home: true, body: `
<section class="tint"><div class="wrap">
  <h2>Care for the whole family</h2><p class="sub">From a child’s first visit to a smile you’ll want to show off.</p>
  <div class="grid3">${SERVICES.slice(0, 3).map(([i, t, d]) => `<a class="card" href="services.html">${ic(IC[i])}<h3>${t}</h3><p>${d}</p></a>`).join('')}</div>
  <div class="row"><a class="btn soft" href="services.html">All services</a></div>
</div></section>
<section><div class="wrap">
  <h2>Your first visit</h2><p class="sub">About an hour, start to finish. No lectures.</p>
  <div class="first"><div><b>Book online</b><span>Pick a time that suits you, evenings included.</span></div><div><b>Quick forms</b><span>Fill them in on your phone before you arrive.</span></div><div><b>Exam &amp; cleaning</b><span>X-rays, a full exam and a gentle cleaning.</span></div><div><b>A clear plan</b><span>What you need, what it costs, and what can wait.</span></div></div>
  <div class="row"><a class="btn soft" href="new-patients.html">What to expect</a></div>
</div></section>
<section class="tint"><div class="wrap grid2">
  <div><h2>Insurance &amp; costs</h2><p class="sub">We file claims for you and tell you what you’ll owe before treatment.</p><ul class="list cols2"><li>Most PPO plans</li><li>Employer dental plans</li><li>HSA &amp; FSA cards</li><li>In-house savings plan</li></ul><div class="row"><a class="btn soft" href="insurance.html">Insurance &amp; payment</a></div></div>
  <div class="price"><span>No insurance? No problem.</span><b>$99</b><p>New-patient exam, cleaning and X-rays.</p><a class="btn" href="book.html">Claim this offer</a></div>
</div></section>
<section><div class="wrap">
  <h2>From the blog</h2><p class="sub">Answers to the questions we hear every week.</p>
  <div class="grid3">${POSTS.map(([s, c, t, d, date]) => `<a class="post" href="blog-${s}.html"><small>${c}</small><h3>${t}</h3><p>${d}</p><time>${date}</time></a>`).join('')}</div>
</div></section>
${cta}` },

  page('services.html', 'Services · Pine Street Dental', 'Checkups, children’s dentistry, fillings and crowns, cosmetic and emergency care.', 'services.html', 'Services', 'Everything your smile needs, <em>under one roof.</em>', 'General, family, cosmetic and emergency dentistry. We’ll always tell you what’s urgent and what can wait.', `
<section class="tint"><div class="wrap"><div class="grid3">${SERVICES.map(([i, t, d, href]) => href ? `<a class="card" href="${href}">${ic(IC[i])}<h3>${t}</h3><p>${d}</p><span class="more">Learn more →</span></a>` : `<div class="card">${ic(IC[i])}<h3>${t}</h3><p>${d}</p></div>`).join('')}</div></div></section>
<section><div class="wrap narrow"><h2>Common questions</h2>${faq([
  ['Do you see kids and adults?', 'Yes. Whole families can book back-to-back appointments, and kids can start from their first tooth.'],
  ['Can I get a crown in one visit?', 'Usually, yes. We design and make most crowns in the office while you wait, in about two hours.'],
  ['What if I’m nervous about the dentist?', 'Tell us when you book. We’ll go slowly, explain each step and agree on a stop signal. Nitrous oxide is available.'],
])}</div></section>${cta}`),

  page('cosmetic.html', 'Cosmetic dentistry · Pine Street Dental', 'Whitening, bonding and veneers, planned step by step.', 'services.html', 'Cosmetic dentistry', 'Whiter, straighter, <em>still you.</em>', 'Subtle changes that look natural. We plan everything with a preview first, so you know what you’re getting.', `
<section class="tint"><div class="wrap"><div class="grid3">
  <div class="card"><h3>Whitening</h3><p>Custom take-home trays or in-office whitening in about 90 minutes.</p><p style="margin-top:12px"><b>From $295</b></p></div>
  <div class="card"><h3>Bonding</h3><p>Tooth-colored resin to fix chips, gaps and uneven edges in a single visit.</p><p style="margin-top:12px"><b>From $250 a tooth</b></p></div>
  <div class="card"><h3>Veneers</h3><p>Thin porcelain shells for a lasting change in shape and color.</p><p style="margin-top:12px"><b>From $1,150 a tooth</b></p></div>
</div><p class="illus" style="margin-top:16px">Example prices for this concept site.</p></div></section>
<section><div class="wrap grid2"><div><h2>How we plan your smile</h2><p class="sub">A free 30-minute consult with photos and a digital preview. No pressure, and nothing happens until you’re sure.</p><a class="btn" href="book.html">Book a cosmetic consult</a></div>
<div><ul class="list"><li>Photos and a digital scan of your teeth</li><li>A preview of the result before any work</li><li>Written costs and timeline</li><li>Payment plans for larger treatment</li></ul></div></div></section>${cta}`),

  page('emergency.html', 'Emergency dentist · Pine Street Dental', 'Same-day emergency dental care for toothaches, chips and lost fillings.', 'services.html', 'Emergency care', 'In pain? <em>Call us today.</em>', 'We keep time open every day for emergencies. Call first thing and we’ll tell you what to do until you get here.', `
<section class="tint"><div class="wrap grid2"><div><h2>We see emergencies for</h2><ul class="list"><li>Severe toothache or swelling</li><li>A chipped, cracked or knocked-out tooth</li><li>A lost filling or crown</li><li>Broken braces wires or dentures</li><li>Bleeding that won’t stop after an extraction</li></ul></div>
<div class="price"><span>Emergency exam &amp; X-ray</span><b>$79</b><p>Credited toward treatment on the same visit.</p><a class="btn" href="contact.html">Call the office</a></div></div></section>
<section><div class="wrap narrow"><h2>Before you get here</h2>${faq([
  ['A tooth was knocked out', 'Pick it up by the top, not the root. Rinse it gently, then keep it in milk or tucked in your cheek. Come in within an hour if you can.'],
  ['My face is swollen', 'Call us right away. If you have trouble breathing or swallowing, go to the emergency room first.'],
  ['A filling fell out', 'Keep the area clean and avoid chewing on that side. A little sugar-free gum can cover a sharp edge until we see you.'],
])}</div></section>`),

  page('new-patients.html', 'New patients · Pine Street Dental', 'What to expect at your first visit, forms and the $99 new-patient offer.', 'new-patients.html', 'New patients', 'Your first visit, <em>start to finish.</em>', 'About an hour, at a pace that suits you. Here’s everything that happens, and what to bring.', `
<section><div class="wrap"><div class="first"><div><b>Book online</b><span>Pick a time that suits you, evenings included.</span></div><div><b>Quick forms</b><span>We text you a link to fill them in on your phone.</span></div><div><b>Exam &amp; cleaning</b><span>X-rays, a full exam and a gentle cleaning.</span></div><div><b>A clear plan</b><span>What you need, what it costs, and what can wait.</span></div></div></div></section>
<section class="tint"><div class="wrap grid2"><div><h2>What to bring</h2><ul class="list"><li>A photo ID</li><li>Your insurance card, if you have one</li><li>A list of medicines you take</li><li>Any recent X-rays from your last dentist (or we can request them)</li></ul></div>
<div class="price"><span>New patients without insurance</span><b>$99</b><p>Exam, cleaning and X-rays at your first visit.</p><a class="btn" href="book.html">Book your first visit</a></div></div></section>
<section><div class="wrap narrow"><h2>Questions</h2>${faq([
  ['How early should I arrive?', 'Ten minutes is plenty if you filled in your forms online.'],
  ['Can I bring my kids to the same appointment?', 'Yes. Book a family block and we’ll see everyone back to back.'],
  ['Will you transfer my records?', 'Yes, with your permission we’ll request X-rays and notes from your previous dentist.'],
])}</div></section>`),

  page('team.html', 'Our team · Pine Street Dental', 'Meet the dentists and hygienists at Pine Street Dental.', 'team.html', 'Our team', 'The people <em>behind the chair.</em>', 'Illustrated portraits for this concept. A live site would use real team photos and bios.', `
<section class="tint"><div class="wrap"><div class="grid3">${[
  ['AR', '#2a7363', 'Dr. Avery Reyes', 'General & cosmetic dentistry', 'Loves a careful cosmetic plan and a well-explained X-ray. Coaches youth soccer on weekends.'],
  ['JM', '#16302b', 'Dr. Jordan Mills', 'Family & pediatric dentistry', 'The dentist kids ask for by name. Keeps a drawer of stickers that never runs out.'],
  ['SK', '#5e9784', 'Sam Kim, RDH', 'Dental hygienist', 'Gentle cleanings and honest advice about flossing (yes, it matters).'],
  ['TB', '#8a6d4f', 'Taylor Brooks', 'Office manager', 'Your go-to for insurance questions, payment plans and rescheduling.'],
  ['MO', '#3f6c8f', 'Morgan Ortiz, RDH', 'Dental hygienist', 'Our evening-hours regular. Great with nervous patients.'],
  ['CL', '#a3586b', 'Casey Lee', 'Dental assistant', 'Makes same-day crowns happen behind the scenes.'],
].map(([i, c, n, r, b]) => `<div class="doc"><div class="ph" style="background:${c}" aria-hidden="true">${i}</div><div class="txt"><b>${n}</b><span>${r}</span><p>${b}</p></div></div>`).join('')}</div></div></section>${cta}`),

  page('insurance.html', 'Insurance & payment · Pine Street Dental', 'Insurance we accept, prices without insurance, and the in-house savings plan.', 'insurance.html', 'Insurance & payment', 'Know the cost <em>before we start.</em>', 'We file claims for you and give you a written estimate before treatment. No surprises at the front desk.', `
<section><div class="wrap grid2"><div><h2>Ways to pay</h2><ul class="list"><li>Most PPO plans, in network or out</li><li>Employer and individual dental plans</li><li>HSA and FSA cards</li><li>Monthly payment plans for larger treatment</li><li>Our in-house savings plan</li><li>Cash and all major cards</li></ul><p class="illus" style="margin-top:14px">Plan names are left out on purpose: a live site lists the networks the practice really belongs to.</p></div>
<div class="price"><span>In-house savings plan</span><b>$29/mo</b><p>Two cleanings and exams a year, X-rays, and 20% off other treatment. No deductible or waiting period.</p><a class="btn" href="contact.html">Ask about the plan</a></div></div></section>
<section class="tint"><div class="wrap"><h2>Prices without insurance</h2><p class="sub">Example prices for this concept site.</p>
<table><thead><tr><th>Visit</th><th>Price</th></tr></thead><tbody><tr><td>New-patient exam, cleaning &amp; X-rays</td><td>$99</td></tr><tr><td>Routine cleaning &amp; exam</td><td>$129</td></tr><tr><td>Emergency exam &amp; X-ray</td><td>$79</td></tr><tr><td>Tooth-colored filling</td><td>from $165</td></tr><tr><td>Same-day crown</td><td>from $1,050</td></tr></tbody></table></div></section>${cta}`),

  page('faq.html', 'FAQ · Pine Street Dental', 'Answers to common questions about visits, costs and care.', '', 'FAQ', 'Questions, <em>answered.</em>', 'The things patients ask us most. Don’t see yours? Call or send us a message.', `
<section><div class="wrap narrow">${faq([
  ['Do you have evening or weekend hours?', 'Evenings until 7pm on Tuesdays and Thursdays, and one Saturday a month for cleanings.'],
  ['How often should I get a cleaning?', 'Every six months for most people. Some patients with gum disease come every three or four months.'],
  ['Are X-rays safe?', 'Our digital X-rays use a very small dose of radiation, and we only take them when we need them.'],
  ['Can I cancel or move my appointment online?', 'Yes, from the link in your confirmation text. Please give us 24 hours’ notice when you can.'],
  ['Do you offer sedation?', 'Nitrous oxide is available for nervous patients. We’ll talk through options at your first visit.'],
  ['What if I need a specialist?', 'We refer to trusted local specialists for things like root canals on back teeth, implants and braces.'],
])}</div></section>${cta}`),

  page('book.html', 'Book online · Pine Street Dental', 'Request an appointment online, evenings included.', '', 'Book online', 'Book your <em>visit.</em>', 'Choose a day and time and we’ll confirm by text within one business hour.', `
<section><div class="wrap grid2">${bookForm}<div><h2>Good to know</h2><ul class="list"><li>New patients: $99 exam, cleaning and X-rays without insurance</li><li>Evenings on Tuesdays and Thursdays</li><li>In pain? <a href="emergency.html">Call for a same-day visit</a></li><li>Forms come by text after you book</li></ul></div></div></section>`),

  page('contact.html', 'Contact · Pine Street Dental', 'Hours, phone and how to reach Pine Street Dental.', 'contact.html', 'Contact', 'Say <em>hello.</em>', 'Call, send a message, or book online. We reply to messages within one business day.', `
<section><div class="wrap grid2"><form data-demo-form="This is a demo, so no message was sent. On a live site it goes to the front desk.">
  <label>Name<input name="name" autocomplete="name" required></label><label>Email<input name="email" type="email" autocomplete="email" required></label>
  <label class="full">Message<textarea name="msg" rows="5" required></textarea></label><button class="btn full" type="submit">Send message</button><p class="note" role="status"></p></form>
  <div><h2>Hours</h2><dl class="hours"><div><dt>Monday</dt><dd>8am–5pm</dd></div><div><dt>Tuesday</dt><dd>8am–7pm</dd></div><div><dt>Wednesday</dt><dd>8am–5pm</dd></div><div><dt>Thursday</dt><dd>8am–7pm</dd></div><div><dt>Friday</dt><dd>8am–2pm</dd></div><div><dt>Saturday</dt><dd>One a month, cleanings only</dd></div></dl>
  <p style="margin-top:24px"><b>Phone</b><br>(864) 555-0177 <span class="illus">(a 555 number that doesn’t ring)</span></p><p class="illus">A live site would show the real address and a map here.</p></div></div></section>`),

  page('blog.html', 'Blog · Pine Street Dental', 'Plain answers to the dental questions we hear every week.', 'blog.html', 'Blog', 'Plain answers, <em>no jargon.</em>', 'Written by our team for patients. The front desk updates this page themselves.', `
<section class="tint"><div class="wrap"><div class="grid3">${POSTS.map(([s, c, t, d, date]) => `<a class="post" href="blog-${s}.html"><small>${c}</small><h3>${t}</h3><p>${d}</p><time>${date}</time></a>`).join('')}</div></div></section>${cta}`),

  ...POSTS.map(([s, c, t, d, date, parts], i) => ({ file: `blog-${s}.html`, title: `${t} · Pine Street Dental`, desc: d, nav: 'blog.html', post: true, crumb: c, h1: t, body: `
<section style="padding-top:20px"><div class="wrap narrow"><article class="prose"><p class="meta">${c} · ${date} · Written by the Pine Street team</p><p class="lede">${d}</p>
${parts.map(([h, p]) => `<h2>${h}</h2><p>${p}</p>`).join('')}
<div class="callout"><b>Questions about your own situation?</b> Call us or <a href="book.html">book a visit</a>. We’re happy to talk it through.</div>
<p><a href="blog.html">← All posts</a>${POSTS[(i + 1) % POSTS.length] ? ` · Next: <a href="blog-${POSTS[(i + 1) % POSTS.length][0]}.html">${POSTS[(i + 1) % POSTS.length][2]}</a>` : ''}</p></article></div></section>` })),
];

function shell(p) {
  const top = p.home ? `<div class="hero">
    <div><span class="eyebrow">Family &amp; cosmetic dentistry</span>
      <h1>A dentist you’ll <em>actually</em> look forward to.</h1>
      <p class="lede">Gentle care for kids and adults, unhurried appointments, and prices explained before we start. New patients are always welcome.</p>
      <div class="row"><a class="btn" href="book.html">Book your first visit</a><a class="btn soft" href="new-patients.html">What to expect</a></div>
      <div class="facts"><span><b>Evenings</b>Tuesdays &amp; Thursdays</span><span><b>Most PPO</b>plans accepted</span><span><b>Same-week</b>new-patient visits</span></div></div>
    <div style="position:relative" aria-hidden="true"><div class="portrait"><div class="sun"></div><div class="hill"></div><div class="hill b"></div></div><div class="offer"><span>New patients without insurance</span><b>$99</b><span>Exam, cleaning and X-rays</span></div></div>
  </div>` : `<div class="page-hero"><div class="crumbs"><a href="index.html">Home</a><span>/</span>${p.post ? `<a href="blog.html">Blog</a><span>/</span>${p.crumb}` : p.eyebrow}</div><h1>${p.h1}</h1>${p.lede ? `<p class="lede">${p.lede}</p>` : ''}</div>`;
  return `${head({ title: p.title.replace(/&(?!amp;)/g, '&amp;'), desc: p.desc, fonts: 'family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=Manrope:wght@400;500;600;700' })}
<body>
<a class="skip" href="#main">Skip to content</a>
${demoBar('pine-street-dental', 'Pine Street Dental')}
<div class="wrap">
  <header>
    <a class="logo" href="index.html"><svg viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="11" fill="#2a7363"/><path d="M17 7l7 11h-4l5 8H9l5-8h-4z" fill="#e7efe9"/></svg>Pine Street Dental</a>
    <nav class="site-nav" aria-label="Main">${navLinks(NAV, p.nav)}</nav>${menuBtn}
    <a class="btn" href="book.html">Book online</a>
  </header>
  ${top}
</div>
<main id="main">
${p.body}
</main>
<footer><div class="wrap">
  <div class="foot">
    <div><b>Pine Street Dental</b>Family &amp; cosmetic dentistry. Evenings on Tuesdays and Thursdays.</div>
    <div><b>Care</b><a href="services.html">Services</a><a href="cosmetic.html">Cosmetic</a><a href="emergency.html">Emergencies</a></div>
    <div><b>Patients</b><a href="new-patients.html">New patients</a><a href="insurance.html">Insurance &amp; payment</a><a href="faq.html">FAQ</a><a href="book.html">Book online</a></div>
    <div><b>Practice</b><a href="team.html">Our team</a><a href="blog.html">Blog</a><a href="contact.html">Contact</a></div>
  </div>
  <div class="foot-bottom"><span>Fictional practice. Example prices and illustrated team.</span><span>A concept by <a href="../../work-pine-street-dental.html">Syndicate Marketing</a></span></div>
</div></footer>
<script src="site.js"></script>
</body>
</html>
`;
}

export default { slug: 'pine-street-dental', css, pages, shell };
