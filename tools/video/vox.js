// Procedural 16x16 block textures + isometric cube drawing (generic voxel art).
(function(){
function rng(s){s=s%2147483646+1;return()=>{s=s*16807%2147483647;return(s-1)/2147483646}}
function tex(fn,seed){const c=document.createElement('canvas');c.width=c.height=16;const g=c.getContext('2d');const r=rng(seed);
  for(let y=0;y<16;y++)for(let x=0;x<16;x++){g.fillStyle=fn(x,y,r());g.fillRect(x,y,1,1)}return c}
const pick=(arr,v)=>arr[Math.min(arr.length-1,Math.floor(v*arr.length))];
const P={
 grassTop:['#4f8f32','#5aa13a','#67b043','#4a8a2e','#72bd4a'],
 dirt:['#7a5232','#6b4628','#8a5f3b','#5c3b21','#93683f'],
 stone:['#7c7f86','#8b8e95','#6f7278','#9a9da4','#666970'],
 plank:['#b48a52','#a37a45','#c39a5f','#956d3b'],
 log:['#6b4a2a','#5a3d22','#7a5732','#4d331c'],
 leaves:['#2f6e2b','#3b8233','#25591f','#46933c','#1f4d1a'],
 sand:['#e3d39a','#d8c78a','#eddfa9','#cdbb7c'],
 water:['#2f6fd6','#3a7ce0','#2a63c4','#4688e8'],
 copper:['#7c7f86','#8b8e95','#6f7278','#d9824a','#b5652f'],
 lime:['#c6ff3d','#b5f02c','#d4ff63','#a7e025'],
 ink:['#14161a','#1b1e23','#101215','#22262c'],
 brick:['#9c4a34','#b25a40','#873f2c','#c9c2b4'],
};
const T={};
T.grassTop=tex((x,y,v)=>pick(P.grassTop,v),11);
T.grassSide=tex((x,y,v)=>y<3+((x*7)%3===0?1:0)?pick(P.grassTop,v):pick(P.dirt,v),12);
T.dirt=tex((x,y,v)=>pick(P.dirt,v),13);
T.stone=tex((x,y,v)=>pick(P.stone,v),14);
T.plank=tex((x,y,v)=>y%4===3?'#7d5a30':pick(P.plank,v*.999),15);
T.logSide=tex((x,y,v)=>x%5===0?'#3f2a17':pick(P.log,v),16);
T.logTop=tex((x,y,v)=>{const d=Math.max(Math.abs(x-7.5),Math.abs(y-7.5));return d>6.5?'#5a3d22':(Math.floor(d)%2?'#b48a52':'#9a7444')},17);
T.leaves=tex((x,y,v)=>v<.12?'#173d14':pick(P.leaves,v),18);
T.sand=tex((x,y,v)=>pick(P.sand,v),19);
T.water=tex((x,y,v)=>pick(P.water,v),20);
T.copper=tex((x,y,v)=>{const ore=((x*13+y*7)%11<2)||((x*5+y*11)%17<2);return ore?pick(P.copper.slice(3),v):pick(P.stone,v)},21);
T.lime=tex((x,y,v)=>(x===0||y===0)?'#e3ff9a':(x===15||y===15)?'#8fc21a':pick(P.lime,v),22);
T.ink=tex((x,y,v)=>(x===0||y===0)?'#2c3038':(x===15||y===15)?'#08090b':pick(P.ink,v),23);
T.brick=tex((x,y,v)=>{const row=Math.floor(y/4),off=row%2?4:0;return (y%4===3||(x+off)%8===7)?'#c9c2b4':pick(P.brick.slice(0,3),v)},24);
T.glow=tex((x,y,v)=>{const d=Math.max(Math.abs(x-7.5),Math.abs(y-7.5));return d<3?'#fff6c2':d<5?'#ffd25c':pick(['#d99a2b','#c2851f'],v)},25);
const BL={grass:[T.grassTop,T.grassSide,T.grassSide],dirt:[T.dirt,T.dirt,T.dirt],stone:[T.stone,T.stone,T.stone],plank:[T.plank,T.plank,T.plank],
 log:[T.logTop,T.logSide,T.logSide],leaves:[T.leaves,T.leaves,T.leaves],sand:[T.sand,T.sand,T.sand],water:[T.water,T.water,T.water],copper:[T.copper,T.copper,T.copper],
 lime:[T.lime,T.lime,T.lime],ink:[T.ink,T.ink,T.ink],brick:[T.brick,T.brick,T.brick],glow:[T.glow,T.glow,T.glow]};
function face(g,img,ox,oy,ux,uy,vx,vy,shade){g.save();g.setTransform(ux/16,uy/16,vx/16,vy/16,ox,oy);g.imageSmoothingEnabled=false;g.drawImage(img,0,0);
  if(shade){g.fillStyle=`rgba(0,0,0,${shade})`;g.fillRect(0,0,16,16)}g.restore()}
// iso cube with top corner at (x,y), half-width s
function cube(g,type,x,y,s,base){const b=BL[type];const m=base||g.getTransform();
  const tr=(px,py)=>[m.a*px+m.c*py+m.e,m.b*px+m.d*py+m.f];const S=(dx,dy)=>[m.a*dx+m.c*dy,m.b*dx+m.d*dy];
  const o1=tr(x,y),u=S(s,s/2),v=S(-s,s/2);face(g,b[0],...o1,...u,...v,0);
  const o2=tr(x-s,y+s/2),u2=S(s,s/2),v2=S(0,s);face(g,b[1],...o2,...u2,...v2,.18);
  const o3=tr(x,y+s),u3=S(s,-s/2),v3=S(0,s);face(g,b[2],...o3,...u3,...v3,.36)}
// flat front-facing block tile with bevel
function tile(g,type,x,y,s){const b=BL[type];g.save();g.imageSmoothingEnabled=false;g.drawImage(b[1]===T.grassSide?T.grassSide:b[0],x,y,s,s);
  g.fillStyle='rgba(255,255,255,.18)';g.fillRect(x,y,s,s*.1);g.fillRect(x,y,s*.1,s);g.fillStyle='rgba(0,0,0,.3)';g.fillRect(x,y+s*.9,s,s*.1);g.fillRect(x+s*.9,y,s*.1,s);g.restore()}
// island heightmap
function island(n,seed){const r=rng(seed),H=[];for(let i=0;i<n;i++){H.push([]);for(let j=0;j<n;j++){const dx=(i-(n-1)/2)/(n/2),dy=(j-(n-1)/2)/(n/2),d=Math.sqrt(dx*dx+dy*dy);
  let h=Math.round((1-d)*4+Math.sin(i*.9)*0.8+Math.cos(j*.7)*0.8+r()*.6);H[i].push(d>1.02?-1:Math.max(0,h))}}return H}
window.VOX={T,BL,cube,tile,island,rng};
})();
