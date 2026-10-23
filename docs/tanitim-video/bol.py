import json, re, subprocess, sys
mp3, sesjson, out = sys.argv[1], sys.argv[2], sys.argv[3]
lines = json.load(open(sesjson, encoding='utf-8'))
dur = float(subprocess.check_output(['ffprobe','-v','error','-show_entries','format=duration','-of','csv=p=0',mp3]))
log = subprocess.run(['ffmpeg','-hide_banner','-i',mp3,'-af','silencedetect=n=-38dB:d=0.18','-f','null','-'],capture_output=True,text=True).stderr
st = [float(x) for x in re.findall(r'silence_start: ([0-9.]+)', log)]
en = [float(x) for x in re.findall(r'silence_end: ([0-9.]+)', log)]
gaps = [(a,b) for a,b in zip(st,en) if a > 0.2 and b < dur - 0.1]
# konuşma başı/sonu
bas = en[0] if st and st[0] < 0.05 else 0.0
son = st[-1] if st and st[-1] > dur - 0.4 else dur
cand = [(a+b)/2 for a,b in gaps]
V = 'aeıioöuüAEIİOÖUÜ'
def w(t): return sum(c in V for c in t) + 2.2*len(re.findall(r'[.;:,…?]\s', t))
W = [w(l['metin']) for l in lines]
N = len(lines); pts = [bas] + cand + [son]
tot = son - bas; k = tot / sum(W)
exp = [x*k for x in W]
import functools
INF = 1e18
# dp[i][j]: ilk i satır, j. noktada bitiş
M = len(pts)
dp = [[INF]*M for _ in range(N+1)]; par = [[-1]*M for _ in range(N+1)]
dp[0][0] = 0
for i in range(1, N+1):
    for j in range(1, M):
        if i == N and j != M-1: continue
        for q in range(j):
            if dp[i-1][q] >= INF: continue
            d = pts[j]-pts[q]; c = dp[i-1][q] + ((d-exp[i-1])/max(exp[i-1],0.8))**2
            if c < dp[i][j]: dp[i][j] = c; par[i][j] = q
j = M-1; seg = []
for i in range(N, 0, -1):
    q = par[i][j]; seg.append((pts[q], pts[j])); j = q
seg.reverse()
import os; os.makedirs(out, exist_ok=True)
for l,(a,b),e in zip(lines, seg, exp):
    a2 = max(0, a-0.05); b2 = b+0.05
    subprocess.run(['ffmpeg','-y','-loglevel','error','-i',mp3,'-ss',f'{a2:.3f}','-to',f'{b2:.3f}','-ar','48000','-ac','1',f"{out}/{l['id']}.wav"],check=True)
    print(f"{l['id']:10s} {a:6.2f}-{b:6.2f} = {b-a:5.2f}s (beklenen {e:4.2f})  {l['metin'][:60]}")
