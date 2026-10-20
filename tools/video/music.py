#!/usr/bin/env python3
"""Original soundtrack for a rendered brief. Reads the engine's cues (scene cuts, text slams, taps)
and writes a stereo WAV timed to them. Three palettes: calm (electric piano), hype (electro),
blocks (chiptune). The seed changes key, progression and melody so no two videos sound the same."""
import sys, json, wave
import numpy as np
from scipy.signal import butter, sosfilt

cues = json.load(open(sys.argv[1])); out = sys.argv[2]
TH = cues['theme']; BPM = cues['bpm']; B = 60 / BPM; END = cues['end'] + .4; ENDS = cues['endScene']
rng = np.random.default_rng(int(cues.get('seed', 1)) * 7919 + 13)
sr = 44100; n = int(END * sr)
bus = {k: np.zeros(n) for k in ['drums', 'bass', 'music', 'pad', 'fx']}

def T(d): return np.arange(int(max(d, .001) * sr)) / sr
def add(k, s, at, g=1.0):
    i = int(at * sr); j = min(n, i + len(s))
    if j > max(0, i): bus[k][max(0, i):j] += s[max(0, -i):j - i] * g
def env(d, a=.003, dec=.1): x = T(d); return np.minimum(1, x / a) * np.exp(-x / dec)
def lp(s, f): return sosfilt(butter(2, min(f, sr / 2.3), 'low', fs=sr, output='sos'), s)
def hp(s, f): return sosfilt(butter(2, f, 'high', fs=sr, output='sos'), s)
N = lambda m: 440 * 2 ** ((m - 69) / 12)
def noise(d): return rng.standard_normal(int(max(d, .001) * sr))
def saw(f, d, det=0): x = T(d); return 2 * ((f * (1 + det) * x) % 1) - 1
def sq(f, d, duty=.5): x = T(d); return np.where((f * x) % 1 < duty, 1., -1.)
def tri(f, d): x = T(d); return 2 * np.abs(2 * ((f * x) % 1) - 1) - 1
def kick(big=1.0): x = T(.45); f = 45 + 190 * np.exp(-x * 35); return np.tanh(np.sin(2 * np.pi * np.cumsum(f) / sr) * np.exp(-x * 6 / big) * 1.5)
def snare(): x = T(.17); return (hp(noise(.17), 1800) * .8 + np.sin(2 * np.pi * 190 * x) * .5) * np.exp(-x * 22)
def clap(): x = T(.28); nz = hp(noise(.28), 1200); e = sum(np.exp(-np.maximum(0, x - o) * 60) * (x >= o) for o in (0, .01, .02)) * np.exp(-x * 14); return nz * e * .7
def hat(open_=False): d = .22 if open_ else .05; x = T(d); return hp(noise(d), 7500) * np.exp(-x * (18 if open_ else 95))
def crash(): x = T(1.8); return hp(noise(1.8), 5000) * np.exp(-x * 2.3) * .5
def boom(): x = T(2.0); f = 30 + 65 * np.exp(-x * 7); return np.sin(2 * np.pi * np.cumsum(f) / sr) * np.exp(-x * 1.5)
def whoosh(d=.35): return lp(noise(d), 2500) * np.sin(np.linspace(0, np.pi, int(d * sr))) ** 2
def riser(d): x = T(d); return lp(noise(d), 3000) * (x / d) ** 2
def thud(): x = T(.35); f = 60 + 90 * np.exp(-x * 30); return np.sin(2 * np.pi * np.cumsum(f) / sr) * np.exp(-x * 12) + lp(noise(.35), 900) * np.exp(-x * 25) * .6
def blip(m, d=.08, duty=.25): return sq(N(m), d, duty) * env(d, .001, d / 2)
def ep(f, d):
    x = T(d); s = np.sin(2 * np.pi * f * x) + .35 * np.sin(2 * np.pi * 2 * f * x) * np.exp(-x * 3) + .12 * np.sin(2 * np.pi * 3.01 * f * x) * np.exp(-x * 6)
    return s * np.exp(-x * 1.6) * (1 + .15 * np.sin(2 * np.pi * 4.5 * x)) * np.minimum(1, x / .004)
def pad(f, d):
    x = T(d); s = sum(np.sin(2 * np.pi * f * (1 + dt) * x + ph) for dt, ph in ((0, 0), (.003, 1), (-.003, 2))) / 3
    return s * np.minimum(1, x / .8) * np.minimum(1, np.maximum(0, d - x) / .8)
def chime(m): x = T(1.6); return (np.sin(2 * np.pi * N(m) * x) + .3 * np.sin(2 * np.pi * N(m) * 4.2 * x) * np.exp(-x * 8)) * np.exp(-x * 2.2)
def stab(ch, d=.18, cut=2600): s = sum(saw(N(m + 24), d, dt) + saw(N(m + 24), d, -dt) for m in ch for dt in (0, .006)); return lp(s, cut) * env(d, .002, d / 3) * .18

key = int(rng.integers(-3, 4))
PROGS = {
 'calm': [[[48,52,55,59],[45,48,52,55],[41,45,48,52],[43,47,50,57]], [[41,45,48,52],[43,47,50,55],[40,43,47,52],[45,48,52,55]], [[48,52,55,59],[43,47,50,55],[45,48,52,55],[41,45,48,52]]],
 'hype': [[[41,44,48],[37,41,44],[44,48,51],[39,43,46]], [[45,48,52],[41,45,48],[43,47,50],[40,43,47]], [[38,41,45],[46,50,53],[41,45,48],[48,52,55]]],
 'blocks': [[[45,48,52],[41,45,48],[48,52,55],[43,47,50]], [[48,52,55],[43,47,50],[45,48,52],[41,45,48]], [[50,53,57],[46,50,53],[48,52,55],[45,48,52]]]}
prog = [[m + key for m in c] for c in PROGS[TH][int(rng.integers(0, 3))]]
ch = lambda t: prog[int(t / (4 * B)) % 4]
cuts = cues['cuts']; slams = cues.get('slams', []); taps = cues.get('taps', []); toasts = cues.get('toasts', [])
beats = np.arange(0, ENDS, B)

if TH == 'calm':
    for bi in range(int(ENDS / (4 * B)) + 1):
        at = bi * 4 * B; c = prog[bi % 4]
        if at >= ENDS: break
        add('pad', sum(pad(N(m + 12), 4 * B + .6) for m in c) / 4, at, .5)
        add('bass', np.sin(2 * np.pi * N(c[0] - 12) * T(4 * B)) * np.minimum(1, T(4 * B) / .02) * np.exp(-T(4 * B) * .5), at, .55)
        for k, (pos, m) in enumerate([(0, c), (1.5, c[1:]), (2.5, c[:3]), (3.5, c[2:])]):
            add('music', sum(ep(N(mm + 12), 1.6) for mm in m) / len(m), at + pos * B, .5)
    start = cuts[1] if len(cuts) > 1 else 0
    for k, at in enumerate(beats):
        if at < start: continue
        if k % 4 in (0, 2): add('drums', kick(.6), at, .55)
        if k % 4 in (1, 3): add('drums', (hp(noise(.12), 1500) * .5 + np.sin(2 * np.pi * 820 * T(.12)) * .4) * np.exp(-T(.12) * 40), at, .3)
        for s in range(2): add('drums', hat(), at + s * B / 2, .14 if s else .08)
    for c0 in cuts[1:]: add('fx', riser(1.0), c0 - 1.0, .1); add('fx', chime(79 + key), c0, .16); add('fx', chime(84 + key), c0 + .08, .09)
    for tp in taps: add('fx', np.sin(2 * np.pi * 1400 * T(.08)) * np.exp(-T(.08) * 60), tp, .2)
    for i, m in enumerate([72, 76, 79]): add('fx', chime(m + key), .2 + i * .45, .13)
    for m in [48, 55, 59, 62, 64, 67]: add('music', ep(N(m + 12 + key), 5.0), ENDS, .16)
    add('pad', sum(pad(N(m + 12 + key), END - ENDS) for m in [48, 52, 55, 59, 62]) / 5, ENDS, .5)
elif TH == 'hype':
    motif = list(rng.choice([0, 2, 3, 5, 7, 10, 12], 8))
    for k, at in enumerate(beats):
        c = ch(at); add('drums', kick(), at, 1.0)
        if k % 2: add('drums', clap(), at, .85)
        add('drums', hat(True), at + B / 2, .32)
        for s in range(4): add('drums', hat(), at + s * B / 4, .16 + .07 * (s % 2))
        for s in range(1, 4):
            f = N(c[0] - 12 + (12 if s == 3 else 0)); d = B / 4 * .9
            add('bass', np.tanh(lp(saw(f, d) + saw(f, d, .01), 900 + 300 * s) * env(d, .002, .06) * 2), at + s * B / 4, .42)
        add('bass', np.sin(2 * np.pi * N(c[0] - 12) * T(B * .95)) * env(B * .95, .004, .25), at, .5)
        for pos in ([.75] if k % 2 == 0 else [.25, .75]): add('music', stab(c), at + pos * B, 1.0)
        if (k // 8) % 2: add('music', sq(N(c[0] + 24 + motif[k % 8]), B / 2 * .8) * env(B / 2 * .8, .002, .06), at + B / 2, .05)
    for c0 in cuts[1:]: add('fx', whoosh(), c0 - .25, .33)
    for s in slams: add('fx', lp(noise(.12), 3000) * np.exp(-T(.12) * 30), s, .25)
    add('drums', kick(2), ENDS, 1.3); add('fx', boom(), ENDS, 1.1); add('fx', crash(), ENDS, .8); add('music', stab(prog[0], 1.8, 6000) * 3, ENDS, 1)
    for m in [41, 44, 48, 53, 56, 60]: add('pad', lp(saw(N(m + 12 + key), END - ENDS) + saw(N(m + 12 + key), END - ENDS, .006), 2200) * env(END - ENDS, .03, 1.1), ENDS + .4, .4)
else:  # blocks
    lead = list(rng.choice([0, 2, 4, 7, 9, 12, 14], 16) + 64 + key)
    for k, at in enumerate(beats):
        c = ch(at); add('drums', kick(), at, .95)
        if k % 2: add('drums', snare(), at, .65)
        for s in range(2): add('drums', hat(), at + s * B / 2 + B / 4, .28)
        for s in range(2): add('bass', tri(N(c[0] - 12 + (12 if s else 0)), B / 2 * .9) * env(B / 2 * .9, .002, .15), at + s * B / 2, .55)
        for s in range(2): add('music', sq(N(lead[(k * 2 + s) % 16]), B / 2 * .85) * env(B / 2 * .85, .003, .12), at + s * B / 2, .075)
        for s in range(4): add('music', blip(c[(s + k) % 3] + 12, B / 4 * .7, .125), at + s * B / 4, .03)
    for c0 in cuts[1:]:
        add('fx', hp(noise(.6), 1500) * np.exp(-T(.6) * 7) + sq(90, .6, .3) * np.exp(-T(.6) * 14) * .3, c0, .65)
    for s in slams: add('fx', thud(), s, .55)
    for t0 in toasts + taps:
        for i, m in enumerate([72, 76, 79]): add('fx', blip(m + key, .06), t0 + i * .05, .1)
    add('drums', kick(2), ENDS, 1.2); add('fx', boom(), ENDS, 1.1); add('fx', crash(), ENDS, .7)
    for m in [57, 60, 64, 69, 72, 76]: add('music', sq(N(m + key), END - ENDS, .25) * env(END - ENDS, .02, 1.0), ENDS + .3, .035)

t = np.arange(n) / sr; ph = (t % B) / B
side = 1 - (.25 if TH == 'calm' else .65) * np.exp(-ph * 9); side[t >= ENDS] = 1
mixd = bus['drums'] * .85 + bus['bass'] * side + bus['music'] * side * (.7 if TH == 'calm' else 1) + bus['pad'] * side * .35 + bus['fx']
mixd = hp(mixd, 30); mixd = np.tanh(mixd * (1.1 if TH == 'calm' else 1.4))
fi = int(.02 * sr); mixd[:fi] *= np.linspace(0, 1, fi)
fe = int(max(ENDS + 1.0, END - 2.0) * sr); mixd[fe:] *= np.linspace(1, 0, n - fe) ** 1.5
mixd /= np.max(np.abs(mixd)) * 1.1
d = int(.012 * sr); R = np.r_[np.zeros(d), mixd[:-d]] * .3 + mixd * .7
pcm = (np.stack([mixd, R], 1) * 32767).astype('<i2')
with wave.open(out, 'wb') as f: f.setnchannels(2); f.setsampwidth(2); f.setframerate(sr); f.writeframes(pcm.tobytes())
print(f'Soundtrack: {END:.1f}s, {TH}, key {key:+d}')
