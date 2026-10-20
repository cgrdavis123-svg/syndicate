// Draws the Copperleaf island (fictional demo server) in isometric voxels.
(function(){
const N=13, H=VOX.island(N,7);
const trees=[[3,4],[9,3],[4,9],[10,9],[6,2]];
const house={i:6,j:6};
// returns list of blocks {i,j,k,type}
function blocks(){const L=[];
  for(let i=0;i<N;i++)for(let j=0;j<N;j++){const h=H[i][j];
    if(h<0){L.push({i,j,k:-1,type:'water',w:1});continue}
    for(let k=-1;k<=h;k++){const type=k===h?(h<=0?'sand':'grass'):(k<h-2?(((i*7+j*3+k)%9)===0?'copper':'stone'):'dirt');L.push({i,j,k,type})}}
  trees.forEach(([i,j])=>{const b=H[i][j];if(b<0)return;for(let k=1;k<=3;k++)L.push({i,j,k:b+k,type:'log'});
    for(let a=-1;a<=1;a++)for(let c=-1;c<=1;c++)for(let k=3;k<=4;k++){if(k===4&&(a||c))continue;if(!a&&!c&&k===3)continue;L.push({i:i+a,j:j+c,k:b+k,type:'leaves'})}});
  const hb=H[house.i][house.j];for(let a=0;a<2;a++)for(let c=0;c<2;c++){L.push({i:house.i+a,j:house.j+c,k:hb+1,type:'plank'});L.push({i:house.i+a,j:house.j+c,k:hb+2,type:a&&c?'glow':'plank'});L.push({i:house.i+a,j:house.j+c,k:hb+3,type:'brick'})}
  L.sort((p,q)=>(p.i+p.j)-(q.i+q.j)||p.k-q.k);return L}
const BLOCKS=blocks();
// progress p 0..1 builds from the bottom up; drop gives blocks a fall-in
function draw(g,cx,cy,s,p=1,t=0){
  const n=BLOCKS.length;BLOCKS.forEach((b,idx)=>{
    const order=(b.k+2)/12*0.6+((b.i+b.j)/(2*N))*0.4, q=Math.max(0,Math.min(1,(p-order*0.85)/0.15));if(q<=0)return;
    const fall=(1-q)*(1-q)*260;const x=cx+(b.i-b.j)*s, y=cy+(b.i+b.j)*s/2-b.k*s-fall+(b.w?Math.sin(t*2+b.i+b.j)*2:0);
    VOX.cube(g,b.type,x,y,s)});}
window.SCENE={draw,N};
})();
