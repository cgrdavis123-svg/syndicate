// Ridgeline Heating & Air — fictional HVAC company. Shows the Starter website (5 pages) + Google/Meta call campaigns.
import { head, demoBar, navLinks, menuBtn } from './common.mjs';

const PHONE = '(864) 555-0142';
const NAV = [['services.html', 'Services'], ['financing.html', 'Financing'], ['service-area.html', 'Service area'], ['contact.html', 'Contact']];
const phoneIcon = '<svg viewBox="0 0 20 20" fill="none"><path d="M5.5 2.5h2.5l1.5 4-2 1.5a9 9 0 0 0 4.5 4.5l1.5-2 4 1.5v2.5a2 2 0 0 1-2 2A13.5 13.5 0 0 1 3.5 4.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
const call = (label = `Call ${PHONE}`, cls = 'call') => `<a class="${cls}" href="tel:+18645550142" data-demo="Demo only, no call placed">${phoneIcon}${label}</a>`;

const css = `
:root { --navy: #0f2a4a; --navy-2: #163a63; --ice: #e8f1fb; --orange: #ff7a1a; --orange-2: #c4570a; --ink: #0b1b2e; --muted: #4f6176; --line: #d6e3f1; --display: "Archivo", "Arial Narrow", sans-serif; --body: "Public Sans", system-ui, sans-serif; }
body { margin: 0; background: #fff; color: var(--ink); font: 17px/1.55 var(--body); }
.demo-bar { background: #000; color: #dbe7f5; } .demo-bar a { color: var(--orange); font-weight: 700; }
.alert { background: var(--orange); color: #1d0d00; font: 700 15px/1.3 var(--body); padding: 11px 16px; text-align: center; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
.top { background: var(--navy); color: #fff; }
header { display: flex; align-items: center; gap: 28px; height: 84px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font: 900 21px/1 var(--display); font-stretch: 112%; letter-spacing: -.01em; text-transform: uppercase; }
.logo small { display: block; font: 600 11px/1 var(--body); letter-spacing: .18em; color: #9fb7d3; margin-top: 5px; } .logo svg { width: 42px; height: 42px; }
.site-nav { display: flex; gap: 26px; margin-left: auto; font-weight: 500; } .site-nav a { text-decoration: none; color: #cfe0f3; } .site-nav a:hover, .site-nav a[aria-current] { color: #fff; }
.call { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 52px; padding: 0 22px; border-radius: 10px; background: var(--orange); color: #1d0d00; font: 800 17px/1 var(--display); font-stretch: 105%; text-decoration: none; border: 0; cursor: pointer; }
.call:hover { background: #ff8b37; } .call svg { width: 20px; height: 20px; } .call.ghost { background: transparent; color: #fff; box-shadow: inset 0 0 0 2px rgba(255,255,255,.35); } .call.navy { background: var(--navy); color: #fff; }
.hero { display: grid; grid-template-columns: 1.15fr .85fr; gap: 48px; align-items: center; padding: 56px 0 96px; }
.avail { display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); padding: 8px 14px; border-radius: 8px; font-weight: 500; font-size: 15px; }
.avail i { width: 10px; height: 10px; border-radius: 50%; background: #3ddc84; box-shadow: 0 0 0 4px rgba(61,220,132,.2); }
h1 { font: 900 clamp(46px, 7.4vw, 100px)/.9 var(--display); font-stretch: 85%; text-transform: uppercase; letter-spacing: -.01em; margin: 22px 0; } h1 span { color: var(--orange); }
.page-hero { padding: 40px 0 70px; } .page-hero h1 { font-size: clamp(44px, 6vw, 80px); margin: 6px 0 16px; } .page-hero .lede { max-width: 680px; }
.lede { font-size: 20px; color: #c3d5ea; max-width: 560px; } .row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 30px; }
.trust { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 34px; font-size: 14.5px; color: #c3d5ea; } .trust b { color: #fff; display: block; font: 800 22px/1 var(--display); }
.thermo { background: var(--navy-2); border-radius: 26px; padding: 30px; border: 1px solid rgba(255,255,255,.1); box-shadow: 0 30px 60px -30px rgba(0,0,0,.6); }
.dial { aspect-ratio: 1; border-radius: 50%; background: conic-gradient(from 220deg, #3b82f6 0deg, #7cc4ff 80deg, var(--orange) 200deg, rgba(255,255,255,.08) 200deg 280deg, transparent 280deg); display: grid; place-items: center; max-width: 300px; margin: 0 auto; }
.dial div { width: 82%; aspect-ratio: 1; border-radius: 50%; background: var(--navy); display: grid; place-items: center; text-align: center; }
.dial b { font: 900 86px/1 var(--display); font-stretch: 80%; } .dial span { display: block; color: #9fb7d3; font-size: 14px; letter-spacing: .1em; text-transform: uppercase; }
.thermo p { text-align: center; margin: 22px 0 0; color: #c3d5ea; } .thermo p b { color: #fff; }
section { padding: 90px 0; } .ice { background: var(--ice); }
h2 { font: 900 clamp(34px, 4.6vw, 60px)/.95 var(--display); font-stretch: 88%; text-transform: uppercase; margin: 0 0 14px; } .sub { color: var(--muted); max-width: 640px; margin: 0 0 44px; font-size: 18px; }
h3 { font: 800 24px/1.1 var(--display); margin: 0 0 8px; }
.svc { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.s { background: #fff; border-radius: 16px; padding: 26px; border: 1px solid var(--line); display: flex; flex-direction: column; gap: 10px; }
.s svg { width: 40px; height: 40px; } .s h3 { margin: 6px 0 0; font-size: 22px; } .s p { margin: 0; color: var(--muted); font-size: 15px; } .s a { margin-top: auto; font-weight: 700; color: var(--orange-2); text-decoration: none; }
.split { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; } .split.top-align { align-items: start; }
.checks { list-style: none; padding: 0; margin: 0; display: grid; gap: 14px; font-size: 18px; } .checks li { display: flex; gap: 12px; } .checks li::before { content: "✓"; color: #fff; background: var(--orange-2); width: 26px; height: 26px; border-radius: 6px; display: grid; place-items: center; font-weight: 900; flex: none; }
.checks.sm { font-size: 16px; gap: 10px; }
.fin { background: var(--navy); color: #fff; border-radius: 22px; padding: 36px; } .fin .big { font: 900 72px/1 var(--display); font-stretch: 80%; color: var(--orange); } .fin p { color: #c3d5ea; }
.kicker { letter-spacing: .12em; text-transform: uppercase; font-size: 13px; font-weight: 700; color: var(--orange-2); } .fin .kicker { color: #9fb7d3; }
.areas { display: flex; flex-wrap: wrap; gap: 10px; } .areas a, .areas span { padding: 10px 16px; border-radius: 8px; background: #fff; border: 1px solid var(--line); font-weight: 500; text-decoration: none; }
.detail { display: grid; grid-template-columns: 1.2fr .8fr; gap: 48px; padding: 56px 0; border-top: 1px solid var(--line); scroll-margin-top: 20px; } .detail:first-child { border-top: 0; padding-top: 0; }
.detail p { color: var(--muted); } .detail > div > svg { width: 56px; height: 56px; } .price-box { background: var(--ice); border-radius: 18px; padding: 26px; align-self: start; } .price-box b { font: 900 44px/1 var(--display); font-stretch: 85%; display: block; margin: 6px 0; }
.price-box p { font-size: 15px; margin: 0 0 18px; }
.steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; counter-reset: s; } .steps div { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
.steps div::before { counter-increment: s; content: counter(s); display: grid; place-items: center; width: 38px; height: 38px; border-radius: 10px; background: var(--navy); color: #fff; font: 900 20px/1 var(--display); margin-bottom: 14px; }
.steps b { display: block; font-size: 18px; margin-bottom: 4px; } .steps span { color: var(--muted); font-size: 15px; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--line); }
th, td { text-align: left; padding: 14px 18px; border-bottom: 1px solid var(--line); } th { background: var(--navy); color: #fff; font-weight: 700; font-size: 15px; } td b { font-family: var(--display); font-size: 20px; }
.towns { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; } .town { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; scroll-margin-top: 20px; }
.town p { margin: 0 0 10px; color: var(--muted); font-size: 15.5px; } .town small { color: var(--orange-2); font-weight: 700; }
.map { background: var(--navy); border-radius: 22px; padding: 20px; } .map svg { display: block; width: 100%; height: auto; }
form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: #fff; border: 1px solid var(--line); padding: 30px; border-radius: 20px; }
label { display: grid; gap: 6px; font-weight: 700; font-size: 14px; } input, select, textarea { font: inherit; padding: 13px 14px; border-radius: 10px; border: 1.5px solid #b6c8dd; background: #fff; }
.full { grid-column: 1 / -1; } form .note { grid-column: 1 / -1; font-size: 14px; color: var(--orange-2); font-weight: 700; margin: 0; }
.hours { display: grid; gap: 8px; margin: 0; } .hours div { display: flex; justify-content: space-between; border-bottom: 1px solid var(--line); padding: 8px 0; } .hours dt { font-weight: 700; } .hours dd { margin: 0; color: var(--muted); }
.cta-band { background: var(--navy); color: #fff; border-radius: 24px; padding: 44px; display: flex; gap: 24px; align-items: center; justify-content: space-between; flex-wrap: wrap; } .cta-band h2 { margin: 0; } .cta-band p { color: #c3d5ea; margin: 8px 0 0; }
footer { background: var(--ink); color: #9fb7d3; padding: 56px 0 40px; font-size: 14.5px; }
.foot { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 28px; } .foot b { color: #fff; display: block; margin-bottom: 10px; } .foot a { display: block; text-decoration: none; padding: 3px 0; } .foot a:hover { color: #fff; }
.foot-bottom { margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.1); display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; } .foot-bottom a { color: var(--orange); }
.sticky-call { display: none; }
@media (max-width: 900px) {
  header > .call { display: none; } .site-nav { background: var(--navy); } .hero, .split, .detail { grid-template-columns: 1fr; } .svc, .steps, .foot { grid-template-columns: 1fr 1fr; } .towns { grid-template-columns: 1fr; } .wrap { padding: 0 18px; } section { padding: 64px 0; }
  .sticky-call { display: flex; position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 5; box-shadow: 0 10px 30px rgba(0,0,0,.35); } body { padding-bottom: 80px; }
  table { font-size: 15px; } th, td { padding: 12px; }
}
@media (max-width: 560px) { .svc, .steps, form, .foot { grid-template-columns: 1fr; } .cta-band { padding: 30px 22px; } }
`;

const svcIcons = {
  ac: '<svg viewBox="0 0 40 40" fill="none" stroke="#0f2a4a" stroke-width="2" aria-hidden="true"><rect x="5" y="9" width="30" height="16" rx="3"/><path d="M10 31v-3M20 33v-5M30 31v-3" stroke="#ff7a1a"/></svg>',
  heat: '<svg viewBox="0 0 40 40" fill="none" stroke="#0f2a4a" stroke-width="2" aria-hidden="true"><path d="M20 6c5 7 9 11 9 17a9 9 0 0 1-18 0c0-6 4-10 9-17Z"/><path d="M20 21c2 3 3 4 3 6a3 3 0 0 1-6 0c0-2 1-3 3-6Z" stroke="#ff7a1a"/></svg>',
  install: '<svg viewBox="0 0 40 40" fill="none" stroke="#0f2a4a" stroke-width="2" aria-hidden="true"><rect x="8" y="6" width="24" height="28" rx="3"/><circle cx="20" cy="20" r="7" stroke="#ff7a1a"/></svg>',
  tune: '<svg viewBox="0 0 40 40" fill="none" stroke="#0f2a4a" stroke-width="2" aria-hidden="true"><path d="M8 20l8 8 16-16" stroke="#ff7a1a"/><circle cx="20" cy="20" r="15"/></svg>',
};

const SERVICES = [
  ['ac-repair', 'ac', 'AC repair', 'Warm air, strange noises, ice on the lines or no power at all. Most repairs are done in one visit because our vans carry the common parts.',
    ['Blowing warm air or not turning on', 'Water leaking near the indoor unit', 'Loud buzzing, rattling or squealing', 'Power bills jumping for no reason'], '$89', 'Diagnostic visit, credited toward the repair if you go ahead.'],
  ['furnace', 'heat', 'Furnace & heat pump service', 'Gas furnaces and heat pumps repaired, cleaned and safety-checked. We test for carbon monoxide on every gas furnace visit.',
    ['No heat or uneven rooms', 'Furnace short-cycles on and off', 'A burning or gas smell (call right away)', 'Heat pump stuck in defrost'], '$89', 'Diagnostic visit, credited toward the repair if you go ahead.'],
  ['new-systems', 'install', 'New systems', 'When a repair doesn’t make sense anymore, we size a new system to your home with a proper load calculation, not a guess from the old unit.',
    ['System is 12+ years old', 'Repairs cost more than half a new unit', 'R-22 refrigerant that’s no longer made', 'Rooms that never get comfortable'], 'Free', 'In-home estimate with three options and written prices.'],
  ['tune-ups', 'tune', 'Tune-ups & maintenance', 'A 21-point check in spring for cooling and in fall for heating. It catches small problems before the first heat wave and keeps warranties valid.',
    ['Coils cleaned and refrigerant checked', 'Electrical connections tightened', 'Drain line flushed', 'Written report with photos'], '$119', 'Per visit, or two visits a year on the Comfort Club plan.'],
];

const AREAS = [
  ['spartanburg', 'Spartanburg', 'Our home base. Technicians are usually in town all day, so same-day visits are most likely here.', 'Usually within 2–4 hours'],
  ['greer', 'Greer', 'From downtown Greer out to the lake neighborhoods, including newer builds with heat pumps.', 'Same day on most weekdays'],
  ['boiling-springs', 'Boiling Springs', 'Lots of 15–20 year-old systems here. We do a high share of replacements and tune-ups.', 'Same day on most weekdays'],
  ['duncan', 'Duncan', 'Homes and small businesses along the 290 corridor.', 'Same or next day'],
  ['inman', 'Inman', 'Including the rural roads north of town; tell us about gravel drives or gates when you book.', 'Same or next day'],
  ['moore', 'Moore', 'Neighborhoods off Highway 221 and around Anderson Mill.', 'Same day on most weekdays'],
  ['roebuck', 'Roebuck', 'Both older brick ranches and new subdivisions.', 'Same or next day'],
  ['lyman', 'Lyman', 'Mill village homes with tight crawl spaces are no problem for our crews.', 'Same or next day'],
];

const quoteForm = (title = 'Request my quote') => `<form data-demo-form="This is a demo, so nothing was sent. On a live site the office gets the request and Site Stats counts a “Quote form sent” goal.">
  <label>Name<input name="name" autocomplete="name" required></label><label>Phone<input name="phone" type="tel" autocomplete="tel" required></label>
  <label>ZIP code<input name="zip" inputmode="numeric" autocomplete="postal-code"></label>
  <label>What do you need?<select name="need"><option>AC repair</option><option>Furnace or heat pump repair</option><option>New system estimate</option><option>Tune-up</option><option>Financing question</option></select></label>
  <label class="full">Anything else?<textarea name="msg" rows="3" placeholder="What’s it doing, and when is a good time?"></textarea></label>
  <button class="call full" type="submit">${title}</button>
  <p class="note full" role="status"></p>
</form>`;

const ctaBand = `<section><div class="wrap"><div class="cta-band"><div><h2>Need it fixed today?</h2><p>Call before 2pm and we can usually get someone out the same day.</p></div><div class="row" style="margin:0">${call()}<a class="call ghost" href="contact.html">Get a free quote</a></div></div></div></section>`;

const pages = [
  { file: 'index.html', title: 'Ridgeline Heating & Air · AC repair and heating in the Upstate', desc: 'Same-day AC repair, furnace service and new systems around Spartanburg.', home: true,
    hero: `<div class="hero">
      <div>
        <span class="avail"><i></i>Technicians available today</span>
        <h1>Cool house <span>by tonight.</span></h1>
        <p class="lede">Licensed, local technicians for AC repair, furnace service and new systems. Upfront pricing before we start, and we clean up after.</p>
        <div class="row">${call(`Call now · ${PHONE}`)}<a class="call ghost" href="contact.html">Get a free quote</a></div>
        <div class="trust"><span><b>Same day</b>repair appointments</span><span><b>Upfront</b>pricing, no surprises</span><span><b>0% APR</b>financing options</span></div>
      </div>
      <div class="thermo" aria-hidden="true"><div class="dial"><div><span>Inside</span><b>72°</b><span>Cooling</span></div></div><p>From call to cool air in as little as <b>4 hours</b>.</p></div>
    </div>`,
    body: `<section class="ice"><div class="wrap">
      <h2>What we fix</h2><p class="sub">Every visit starts with a diagnosis and a written price. You decide before we touch anything.</p>
      <div class="svc">${SERVICES.map(([id, ic, name, text]) => `<div class="s">${svcIcons[ic]}<h3>${name}</h3><p>${text.split('. ')[0]}.</p><a href="services.html#${id}">Learn more →</a></div>`).join('')}</div>
    </div></section>
    <section><div class="wrap split">
      <div><h2>Why homeowners call us first</h2>
        <ul class="checks"><li>We answer the phone, 7am to 9pm, every day.</li><li>A written price before any work starts.</li><li>Background-checked, licensed technicians.</li><li>Shoe covers on, mess cleaned up, every time.</li></ul></div>
      <div class="fin"><span class="kicker">New system financing</span><div class="big">$89/mo</div><p>Illustrative monthly payment on a new AC system with approved credit. Your real options come with your estimate.</p><a class="call" href="financing.html">See financing options</a></div>
    </div></section>
    <section class="ice"><div class="wrap">
      <h2>Where we work</h2><p class="sub">Based in the Upstate, with technicians across the area every day.</p>
      <div class="areas">${AREAS.map(([id, n]) => `<a href="service-area.html#${id}">${n}</a>`).join('')}</div>
    </div></section>
    <section><div class="wrap">
      <h2>How a repair visit works</h2><p class="sub">No surprise bills. No pressure to replace something that can be fixed.</p>
      <div class="steps"><div><b>Call or book</b><span>Pick a two-hour window. We text when we’re on the way.</span></div><div><b>Diagnosis</b><span>We find the problem and show you, with photos.</span></div><div><b>Written price</b><span>You approve the price before any work starts.</span></div><div><b>Fixed &amp; tidy</b><span>We test the system and clean up after ourselves.</span></div></div>
    </div></section>
    ${ctaBand}` },

  { file: 'services.html', title: 'Services · Ridgeline Heating & Air', desc: 'AC repair, furnace and heat pump service, new systems and tune-ups.', nav: 'services.html',
    kicker: 'Services', h1: 'Heating &amp; cooling, <span>done right.</span>', lede: 'Four things we do every day. Each one starts with a written price before any work begins.',
    body: `<section><div class="wrap">
      ${SERVICES.map(([id, ic, name, text, signs, price, priceNote]) => `<div class="detail" id="${id}">
        <div>${svcIcons[ic]}<h2 style="margin-top:12px">${name}</h2><p>${text}</p>
          <h3 style="margin-top:28px">Signs you need us</h3><ul class="checks sm">${signs.map(s => `<li>${s}</li>`).join('')}</ul></div>
        <div class="price-box"><span class="kicker">Starting at</span><b>${price}</b><p>${priceNote}</p>${call('Call to book', 'call')}<p style="margin:14px 0 0"><a href="contact.html">Or request a quote online →</a></p></div>
      </div>`).join('')}
    </div></section>
    <section class="ice"><div class="wrap split top-align">
      <div><h2>Comfort Club</h2><p class="sub">Two tune-ups a year, priority scheduling and 15% off repairs.</p>
        <ul class="checks sm"><li>Spring cooling and fall heating tune-ups</li><li>Front of the line on busy days</li><li>No overtime charges, evenings or weekends</li><li>15% off any repair</li></ul></div>
      <div class="fin"><span class="kicker">Comfort Club</span><div class="big">$16/mo</div><p>Billed monthly, cancel online any time.</p><a class="call" href="contact.html">Join the club</a></div>
    </div></section>
    <section><div class="wrap" style="max-width:820px">
      <h2>Questions</h2>
      <details class="faq"><summary>Do you charge extra for nights and weekends?</summary><p>Evenings and weekends have a $49 after-hours fee, waived for Comfort Club members.</p></details>
      <details class="faq"><summary>Which brands do you work on?</summary><p>All major brands, including Carrier, Trane, Lennox, Goodman, Rheem and heat pumps from most makers.</p></details>
      <details class="faq"><summary>Is the diagnostic fee really credited?</summary><p>Yes. If you approve the repair on the same visit, the $89 comes off the price.</p></details>
      <details class="faq"><summary>What warranty do repairs have?</summary><p>One year on parts and labor for repairs. New systems come with a 10-year parts warranty when registered.</p></details>
    </div></section>
    ${ctaBand}` },

  { file: 'financing.html', title: 'Financing · Ridgeline Heating & Air', desc: 'Monthly payment options on new heating and cooling systems.', nav: 'financing.html',
    kicker: 'Financing', h1: 'A new system, <span>monthly.</span>', lede: 'Spread the cost of a new system over time. You’ll see real numbers with your estimate, before you sign anything.',
    body: `<section><div class="wrap">
      <h2>How it works</h2><p class="sub">Checking your options takes about five minutes and doesn’t affect your credit score.</p>
      <div class="steps"><div><b>Free estimate</b><span>We visit, measure and give you three written options.</span></div><div><b>Check your rate</b><span>A soft credit check shows what you qualify for.</span></div><div><b>Pick a plan</b><span>Choose the term and payment that fit your budget.</span></div><div><b>Install</b><span>Most installs take one day. First payment in about 30 days.</span></div></div>
    </div></section>
    <section class="ice"><div class="wrap">
      <h2>Example plans</h2><p class="sub">Illustrative only, on a $7,500 system. Real terms depend on the lender and your credit.</p>
      <table><thead><tr><th>Plan</th><th>Term</th><th>APR</th><th>Monthly</th></tr></thead><tbody>
        <tr><td>Same as cash</td><td>12 months</td><td>0%*</td><td><b>$625</b></td></tr>
        <tr><td>Low monthly</td><td>60 months</td><td>7.99%</td><td><b>$152</b></td></tr>
        <tr><td>Lowest monthly</td><td>120 months</td><td>9.99%</td><td><b>$99</b></td></tr>
      </tbody></table>
      <p class="illus" style="margin-top:14px">* Example figures for this concept site. No real lender or offer is shown. On a live site this table holds the client’s actual lender terms and required disclosures.</p>
    </div></section>
    <section><div class="wrap" style="max-width:820px">
      <h2>Questions</h2>
      <details class="faq"><summary>Does checking my rate hurt my credit?</summary><p>No. The first check is a soft pull. A full credit check only happens if you accept a plan.</p></details>
      <details class="faq"><summary>Can I pay it off early?</summary><p>Yes, with no prepayment penalty on any plan we offer.</p></details>
      <details class="faq"><summary>Can I finance a repair?</summary><p>Repairs over $1,000 can be financed on the same plans.</p></details>
    </div></section>
    ${ctaBand}` },

  { file: 'service-area.html', title: 'Service area · Ridgeline Heating & Air', desc: 'AC and heating service across Spartanburg, Greer, Boiling Springs, Duncan, Inman, Moore, Roebuck and Lyman.', nav: 'service-area.html',
    kicker: 'Service area', h1: 'Local to <span>the Upstate.</span>', lede: 'Eight towns, technicians on the road every day. Find yours to see how fast we can usually get there.',
    body: `<section><div class="wrap split top-align">
      <div class="map" aria-hidden="true"><svg viewBox="0 0 400 300"><rect width="400" height="300" rx="14" fill="#163a63"/>
        <path d="M30 230 C90 180 140 210 190 160 S300 120 370 60" stroke="#2c5584" stroke-width="22" fill="none" stroke-linecap="round"/>
        ${[[200, 150, 'Spartanburg', 1], [110, 175, 'Greer'], [215, 85, 'Boiling Springs'], [80, 220, 'Duncan'], [140, 70, 'Inman'], [175, 230, 'Moore'], [255, 205, 'Roebuck'], [55, 125, 'Lyman']].map(([x, y, n, big]) => `<circle cx="${x}" cy="${y}" r="${big ? 11 : 7}" fill="#ff7a1a"/><text x="${x + 14}" y="${y + 5}" fill="#fff" font-size="${big ? 16 : 13}" font-family="Public Sans, sans-serif" font-weight="${big ? 700 : 500}">${n}</text>`).join('')}
      </svg></div>
      <div><h2>Not sure if you’re covered?</h2><p class="sub">Call and ask. If you’re just outside our area we’ll often still come, and we’ll tell you upfront if there’s a travel fee.</p>${call()}</div>
    </div></section>
    <section class="ice"><div class="wrap"><div class="towns">
      ${AREAS.map(([id, n, text, eta]) => `<div class="town" id="${id}"><h3>${n}</h3><p>${text}</p><small>${eta}</small></div>`).join('')}
    </div></div></section>
    ${ctaBand}` },

  { file: 'contact.html', title: 'Contact & quotes · Ridgeline Heating & Air', desc: 'Call or request a free quote for AC repair, heating service or a new system.', nav: 'contact.html',
    kicker: 'Contact', h1: 'Get a <span>free quote.</span>', lede: 'Tell us what’s going on and we’ll call you back within the hour during business hours.',
    body: `<section><div class="wrap split top-align">
      ${quoteForm()}
      <div>
        <h2 style="font-size:36px">Call us</h2><p style="margin:0 0 18px;color:var(--muted)">Fastest for same-day repairs.</p>${call()}
        <h2 style="font-size:36px;margin-top:40px">Hours</h2>
        <dl class="hours"><div><dt>Phones</dt><dd>7am–9pm, every day</dd></div><div><dt>Repairs</dt><dd>Mon–Sat, 7am–7pm</dd></div><div><dt>Emergencies</dt><dd>Sundays and after hours</dd></div></dl>
        <p class="illus" style="margin-top:22px">Office address and license number would appear here on a live site.</p>
      </div>
    </div></section>` },
];

function shell(p) {
  const top = p.home ? p.hero : `<div class="page-hero"><div class="crumbs"><a href="index.html">Home</a><span>/</span>${p.kicker}</div><h1>${p.h1}</h1><p class="lede">${p.lede}</p></div>`;
  return `${head({ title: p.title.replace(/&/g, '&amp;'), desc: p.desc, fonts: 'family=Archivo:wdth,wght@75..125,500..900&family=Public+Sans:wght@400;500;700' })}
<body>
<a class="skip" href="#main">Skip to content</a>
${demoBar('ridgeline-air', 'Ridgeline Heating &amp; Air')}
<div class="alert">AC out? Same-day repair appointments still open today. Call ${PHONE}</div>
<div class="top"><div class="wrap">
  <header>
    <a class="logo" href="index.html"><svg viewBox="0 0 42 42" aria-hidden="true"><rect width="42" height="42" rx="9" fill="#ff7a1a"/><path d="M8 29l9-13 6 8 4-5 7 10z" fill="#0f2a4a"/><circle cx="30" cy="12" r="4" fill="#fff"/></svg><span>Ridgeline<small>Heating &amp; Air</small></span></a>
    <nav class="site-nav" aria-label="Main">${navLinks(NAV, p.nav)}</nav>${menuBtn}
    ${call(PHONE)}
  </header>
  ${top}
</div></div>
<main id="main">
${p.body}
</main>
<footer><div class="wrap">
  <div class="foot">
    <div><b>Ridgeline Heating &amp; Air</b>Licensed &amp; insured heating and cooling for the Upstate. Phones answered 7am–9pm, every day.</div>
    <div><b>Services</b>${SERVICES.map(([id, , n]) => `<a href="services.html#${id}">${n}</a>`).join('')}</div>
    <div><b>Company</b><a href="financing.html">Financing</a><a href="service-area.html">Service area</a><a href="contact.html">Contact</a></div>
    <div><b>Towns</b>${AREAS.slice(0, 5).map(([id, n]) => `<a href="service-area.html#${id}">${n}</a>`).join('')}</div>
  </div>
  <div class="foot-bottom"><span>Fictional business · ${PHONE} is a 555 number that doesn’t ring</span><span>A concept by <a href="../../work-ridgeline-air.html">Syndicate Marketing</a></span></div>
</div></footer>
${call(`Tap to call · ${PHONE}`, 'call sticky-call')}
<script src="site.js"></script>
</body>
</html>
`;
}

export default { slug: 'ridgeline-air', css, pages, shell };
