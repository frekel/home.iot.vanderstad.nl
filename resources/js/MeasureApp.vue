<script setup lang="ts">
import {computed,onMounted,ref,watch} from 'vue';
import {ArrowLeft,Check,ChevronLeft,ChevronRight,House,RefreshCcw,Ruler,SkipForward,WandSparkles} from '@lucide/vue';
import {house} from './house';
import {furnitureState,refreshFurniture,type FurnitureItem,type FurnitureState} from './furniture';

type Dimension='width'|'depth'|'height';
type RoomOption={key:string;id:string;floor:string;label:string;floorLabel:string;items:FurnitureItem[]};
type Zone={name:string;parent:string|null};
type Layout={floors:Record<string,string>;rooms:Record<string,string>;aliases?:Record<string,string>};
type HomeStatus={zones?:Record<string,Zone>;layout?:Layout};

const prefixes:Record<string,string>={ground:'BG',upper:'V1',attic:'Z'};
const floorNames:Record<string,string>={ground:'Begane grond',upper:'Eerste verdieping',attic:'Zolder'};
const names:Record<string,string>={sofa:'Bank',armchair:'Fauteuil',cabinet:'Kast',corner_sofa:'Hoekbank',split_door_cabinet:'Kast · deuren 60 / 40 cm',open_shelving:'Open plankenkast',dog_house:'Hondenhok',wall_cabinet_set:'Drie hangkastjes',american_fridge:'Amerikaanse koelkast',shoe_rack:'Schoenenrek',breakfast_bar:'Bar',wall_shelf:'Wandplank',drawer_cabinet:'Ladekastje',rotating_mirror_cabinet:'Draaikast met spiegel',bed:'Bed',tv:'Tv',chair:'Stoel',fridge:'Koelkast',cooker:'Fornuis',sink:'Spoelbak / wastafel',toilet:'Toilet',bath:'Bad',plant:'Plant',computer:'Computer',table:'Tafel / bureau',washer:'Wasmachine / droger',open_wardrobe:'Open kledingkast',winder_stairs:'Halfslagtrap',projector_screen:'Beamerscherm',shower:'Douche',water_heater:'Boiler',heating_boiler:'Cv-ketel',infrared_panel:'Infraroodpaneel',mirror:'Wandspiegel',basket_cabinet:'Ladekast met mandjes'};
const limits:Record<Dimension,{min:number;max:number}>={width:{min:5,max:1100},depth:{min:1,max:550},height:{min:1,max:400}};
const measuredKey='home-iot-measure-mode-measured-v1';
const skippedKey='home-iot-measure-mode-skipped-v1';

const loading=ref(true),saving=ref(false),building=ref(false),error=ref(''),notice=ref('');
const roomKey=ref(''),itemIndex=ref(0),roundFinished=ref(false);
const measured=ref<Set<string>>(new Set()),skipped=ref<Set<string>>(new Set());
const form=ref<Record<Dimension,string>>({width:'',depth:'',height:''});
const zoneNames=ref<Record<string,Zone>>({});
const layout=ref<Layout>({floors:{},rooms:{},aliases:{}});

function reference(i:FurnitureItem){return `${prefixes[i.floor]??i.floor}-${i.id==='levi-tv-cabinet'?'901':i.id==='levi-tv'?'902':i.id}`}
function label(i:FurnitureItem){return i.id==='levi-tv-cabinet'?'Kast aan voeteneinde':i.id==='levi-tv'?'Tv aan muur':names[i.kind]??i.kind}
function contains(p:number[][],x:number,y:number){let inside=false;for(let i=0,j=p.length-1;i<p.length;j=i++){const a=p[i]!,b=p[j]!;if((a[1]!>y)!==(b[1]!>y)&&x<(b[0]!-a[0]!)*(y-a[1]!)/(b[1]!-a[1]!)+a[0]!)inside=!inside}return inside}
function roomFor(i:FurnitureItem){return house.floors.find(f=>f.id===i.floor)?.rooms.find(r=>contains(r.polygon,i.x,i.y))}
function canonicalRoom(id:string){return layout.value.aliases?.[id]??id}
function roomLabel(id:string,fallback:string){const canonical=canonicalRoom(id);return zoneNames.value[layout.value.rooms[canonical]??'']?.name??fallback}
function floorLabel(id:string,fallback:string){return zoneNames.value[layout.value.floors[id]??'']?.name??floorNames[id]??fallback}
function cm(value:number){return String(Math.round(value*100000)/1000)}
function csrf(){return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??''}
function mapPoint(x:number,y:number,floor:string){const plan=house.floors.find(f=>f.id===floor);return plan?.display_mirrored?[-x,-y]:[x,y]}
function footprint(i:FurnitureItem){const a=i.rotation*Math.PI/180;return [[-1,-1],[1,-1],[1,1],[-1,1]].map(([sx,sy])=>{const x=sx!*i.width/2,y=sy!*i.depth/2;return mapPoint(i.x+x*Math.cos(a)-y*Math.sin(a),i.y+x*Math.sin(a)+y*Math.cos(a),i.floor).join(',')}).join(' ')}
function clearField(key:Dimension){form.value[key]='';error.value=''}
async function refreshHomeyLabels(){
 try{
  const response=await fetch('/dashboard/status',{headers:{Accept:'application/json'},signal:AbortSignal.timeout(12000)});
  const data=await response.json() as HomeStatus;
  if(data.layout)layout.value=data.layout;
  if(data.zones)zoneNames.value=data.zones;
 }catch{/* Keep plan labels as fallback when Homey is unavailable. */}
}

const catalogueRooms=computed<RoomOption[]>(()=>{
 const items=furnitureState.value?.items??[];
 const grouped=new Map<string,RoomOption>();
 for(const floor of house.floors){
  for(const planRoom of floor.rooms){
   const roomItems=items.filter(i=>i.floor===floor.id&&roomFor(i)?.id===planRoom.id);
   if(!roomItems.length)continue;
   const canonical=canonicalRoom(planRoom.id);
   const key=`${floor.id}:${canonical}`;
   const existing=grouped.get(key);
   if(existing){existing.items.push(...roomItems);continue}
   grouped.set(key,{key,id:canonical,floor:floor.id,label:roomLabel(planRoom.id,planRoom.label),floorLabel:floorLabel(floor.id,floor.label),items:[...roomItems]});
  }
 }
 const result=[...grouped.values()];
 for(const room of result)room.items.sort((a,b)=>reference(a).localeCompare(reference(b),'nl'));
 const placed=new Set(result.flatMap(r=>r.items.map(reference)));
 const unknown=items.filter(i=>!placed.has(reference(i)));
 if(unknown.length)result.push({key:'unknown',id:'unknown',floor:'',label:'Plaatsing controleren',floorLabel:'Overig',items:unknown});
 return result;
});
const rooms=computed<RoomOption[]>(()=>catalogueRooms.value.map(r=>({...r,items:r.items.filter(i=>!measured.value.has(reference(i)))})).filter(r=>r.items.length));
const room=computed(()=>rooms.value.find(r=>r.key===roomKey.value));
const roomItems=computed(()=>room.value?.items??[]);
const activeRoomAllItems=computed(()=>catalogueRooms.value.find(r=>r.key===roomKey.value)?.items??[]);
const current=computed(()=>roomItems.value[itemIndex.value]);
const allItems=computed(()=>catalogueRooms.value.flatMap(r=>r.items));
const processed=computed(()=>new Set([...measured.value,...skipped.value]));
const measuredCount=computed(()=>allItems.value.filter(i=>measured.value.has(reference(i))).length);
const skippedCount=computed(()=>allItems.value.filter(i=>skipped.value.has(reference(i))).length);
const processedCount=computed(()=>allItems.value.filter(i=>processed.value.has(reference(i))).length);
const remainingCount=computed(()=>Math.max(0,allItems.value.length-processedCount.value));
const roomMeasured=computed(()=>activeRoomAllItems.value.filter(i=>measured.value.has(reference(i))).length);
const roomProcessed=computed(()=>activeRoomAllItems.value.filter(i=>processed.value.has(reference(i))).length);
const progress=computed(()=>allItems.value.length?Math.round(processedCount.value/allItems.value.length*100):0);
const modelOutdated=computed(()=>!!furnitureState.value&&furnitureState.value.model_revision!==furnitureState.value.revision);
const buildBusy=computed(()=>building.value||['queued','building'].includes(furnitureState.value?.status??''));
const roomMapPolygons=computed(()=>{
 const active=room.value;if(!active?.floor)return [] as number[][][];
 const floor=house.floors.find(f=>f.id===active.floor);
 return (floor?.rooms??[]).filter(r=>canonicalRoom(r.id)===active.id).map(r=>r.polygon.map(p=>mapPoint(p[0]!,p[1]!,active.floor)));
});
const roomMapViewBox=computed(()=>{
 const points=roomMapPolygons.value.flat();if(!points.length)return '0 0 1 1';
 const xs=points.map(p=>p[0]!),ys=points.map(p=>p[1]!);const pad=.35;
 const minX=Math.min(...xs)-pad,maxX=Math.max(...xs)+pad,minY=Math.min(...ys)-pad,maxY=Math.max(...ys)+pad;
 return `${minX} ${minY} ${Math.max(.5,maxX-minX)} ${Math.max(.5,maxY-minY)}`;
});

function loadProgress(){
 try{measured.value=new Set(JSON.parse(localStorage.getItem(measuredKey)??'[]'));skipped.value=new Set(JSON.parse(localStorage.getItem(skippedKey)??'[]'))}catch{measured.value=new Set();skipped.value=new Set()}
}
function persist(){localStorage.setItem(measuredKey,JSON.stringify([...measured.value]));localStorage.setItem(skippedKey,JSON.stringify([...skipped.value]))}
function resetProgress(){if(!confirm('Nieuwe meetronde starten? Alleen de vinkjes en overgeslagen status worden gewist; opgeslagen maten blijven behouden.'))return;measured.value=new Set();skipped.value=new Set();persist();roundFinished.value=false;roomKey.value=rooms.value[0]?.key??'';itemIndex.value=0;notice.value='Nieuwe meetronde gestart.'}
function setMeasured(id:string){const next=new Set(measured.value);next.add(id);measured.value=next;const skip=new Set(skipped.value);skip.delete(id);skipped.value=skip;persist()}
function setSkipped(id:string){const next=new Set(skipped.value);next.add(id);skipped.value=next;const done=new Set(measured.value);done.delete(id);measured.value=done;persist()}
function fillForm(){const i=current.value;if(!i)return;form.value={width:cm(i.width),depth:cm(i.depth),height:cm(i.height)};error.value='';notice.value=''}
function chooseRoom(){itemIndex.value=0;roundFinished.value=false;fillForm()}
function next(){
 if(!room.value)return;
 if(itemIndex.value<roomItems.value.length-1){itemIndex.value++;return}
 const roomIndex=rooms.value.findIndex(r=>r.key===roomKey.value);
 if(roomIndex>=0&&roomIndex<rooms.value.length-1){roomKey.value=rooms.value[roomIndex+1]!.key;itemIndex.value=0;return}
 roundFinished.value=true;
}
function previous(){
 if(roundFinished.value){roundFinished.value=false;return}
 if(itemIndex.value>0){itemIndex.value--;return}
 const roomIndex=rooms.value.findIndex(r=>r.key===roomKey.value);
 if(roomIndex>0){const previousRoom=rooms.value[roomIndex-1]!;roomKey.value=previousRoom.key;itemIndex.value=Math.max(0,previousRoom.items.length-1)}
}
function skip(){if(!current.value)return;setSkipped(reference(current.value));next()}
function firstUnprocessed(){
 for(const candidateRoom of rooms.value){const index=candidateRoom.items.findIndex(i=>!processed.value.has(reference(i)));if(index>=0){roomKey.value=candidateRoom.key;itemIndex.value=index;roundFinished.value=false;return}}
 if(rooms.value.length){roomKey.value=rooms.value[rooms.value.length-1]!.key;itemIndex.value=Math.max(0,rooms.value[rooms.value.length-1]!.items.length-1);roundFinished.value=true}else roundFinished.value=true;
}
function advanceAfterSave(previousRoomKey:string,previousIndex:number){
 const catalogueIndex=catalogueRooms.value.findIndex(r=>r.key===previousRoomKey);
 const sameRoom=rooms.value.find(r=>r.key===previousRoomKey);
 const nextRoom=rooms.value.find(r=>catalogueRooms.value.findIndex(c=>c.key===r.key)>catalogueIndex);
 if(sameRoom&&previousIndex<sameRoom.items.length){roomKey.value=sameRoom.key;itemIndex.value=previousIndex;roundFinished.value=false}
 else if(nextRoom){roomKey.value=nextRoom.key;itemIndex.value=0;roundFinished.value=false}
 else if(sameRoom){roomKey.value=sameRoom.key;itemIndex.value=Math.max(0,sameRoom.items.length-1);roundFinished.value=false}
 else if(rooms.value.length){roomKey.value=rooms.value[0]!.key;itemIndex.value=0;roundFinished.value=false}
 else roundFinished.value=true;
 window.scrollTo({top:0,left:0,behavior:'smooth'});
}
async function saveAndNext(){
 const item=current.value,state=furnitureState.value;if(!item||!state||saving.value)return;
 const previousRoomKey=roomKey.value,previousIndex=itemIndex.value;
 const values={} as Record<Dimension,number>;
 for(const key of ['width','depth','height'] as Dimension[]){const value=Number(form.value[key]);if(!Number.isFinite(value)||value<limits[key].min||value>limits[key].max){error.value=`${key==='width'?'Breedte':key==='depth'?'Diepte':'Hoogte'} moet tussen ${limits[key].min} en ${limits[key].max} cm liggen.`;return}values[key]=value}
 saving.value=true;error.value='';notice.value='';
 try{
  const response=await fetch('/dashboard/furniture',{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({revision:state.revision,items:[{floor:item.floor,id:item.id,width:values.width,depth:values.depth,height:values.height,base_z:Number(cm(item.base_z))}]}),signal:AbortSignal.timeout(20000)});
  const data=await response.json();
  if(!response.ok)throw new Error(response.status===409?'De meubelgegevens zijn ondertussen gewijzigd. Herlaad de meetmodus en probeer opnieuw.':data.errors?Object.values(data.errors).flat().join(' '):data.message??'Opslaan mislukt.');
  furnitureState.value=data as FurnitureState;setMeasured(reference(item));advanceAfterSave(previousRoomKey,previousIndex);
 }catch(e){error.value=e instanceof Error?e.message:'Opslaan mislukt. Controleer de verbinding.'}finally{saving.value=false}
}
async function build(){
 const state=furnitureState.value;if(!state||buildBusy.value)return;building.value=true;error.value='';
 try{const response=await fetch('/dashboard/furniture/build',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({revision:state.revision}),signal:AbortSignal.timeout(20000)});const data=await response.json();if(!response.ok)throw new Error(data.message??'Opbouwen starten mislukt.');furnitureState.value=data as FurnitureState;notice.value='Plattegrond staat in de wachtrij. Je kunt deze pagina sluiten.'}catch(e){error.value=e instanceof Error?e.message:'Opbouwen starten mislukt.'}finally{building.value=false}
}

watch(()=>current.value?reference(current.value):'',fillForm);
watch(roomKey,()=>{itemIndex.value=Math.min(itemIndex.value,Math.max(0,roomItems.value.length-1))});
onMounted(async()=>{loadProgress();try{await Promise.all([refreshFurniture(),refreshHomeyLabels()]);const requested=new URLSearchParams(location.search).get('room');const target=requested?rooms.value.find(r=>r.id===canonicalRoom(requested)||r.key===requested):undefined;if(target){roomKey.value=target.key;itemIndex.value=0}else firstUnprocessed()}catch{error.value='De meubelgegevens konden niet worden geladen.'}finally{loading.value=false;fillForm()}});
</script>

<template>
<div class="measure-page">
 <header class="measure-header">
  <a class="measure-back" href="/"><ArrowLeft :size="20"/><span>Home</span></a>
  <div><span class="measure-kicker">VAN DER STAD HOME</span><h1><Ruler :size="25"/> Meetmodus</h1></div>
  <button class="measure-reset" type="button" @click="resetProgress"><RefreshCcw :size="17"/><span>Nieuwe ronde</span></button>
 </header>

 <main class="measure-main">
  <p v-if="error" class="measure-alert" role="alert">{{error}}</p>
  <p v-else-if="notice" class="measure-notice" role="status">{{notice}}</p>

  <section v-if="loading" class="measure-card measure-loading">Meubels laden…</section>
  <section v-else-if="roundFinished" class="measure-card measure-finished">
   <span class="measure-finished-icon"><Check :size="28"/></span>
   <h2>{{remainingCount ? 'Nog niet helemaal klaar' : 'Meetronde afgerond'}}</h2>
   <p v-if="remainingCount">{{remainingCount}} meubels zijn nog niet gemeten of overgeslagen. Je opgeslagen maten zijn al bewaard.</p>
   <p v-else>{{measuredCount}} meubels gemeten<span v-if="skippedCount"> en {{skippedCount}} overgeslagen</span>. Je maten zijn al opgeslagen.</p>
   <button v-if="remainingCount" class="measure-primary" @click="firstUnprocessed"><Ruler :size="19"/>Ga naar eerste open meubel</button>
   <button v-else-if="modelOutdated" class="measure-primary" :disabled="buildBusy" @click="build"><WandSparkles :size="19"/>{{buildBusy?'Plattegrond wordt opgebouwd…':'Plattegrond bijwerken'}}</button>
   <p v-else>De 3D-plattegrond is al bijgewerkt met de huidige revisie.</p>
   <button class="measure-secondary" @click="previous"><ChevronLeft :size="18"/>Laatste meubel bekijken</button>
  </section>

  <template v-else-if="current && room">
   <section class="measure-roombar">
    <label>Kamer<select v-model="roomKey" @change="chooseRoom"><option v-for="r in rooms" :key="r.key" :value="r.key">{{r.floorLabel}} · {{r.label}} ({{r.items.length}} open)</option></select></label>
    <div><strong>{{roomMeasured}} / {{activeRoomAllItems.length}}</strong><span>gemeten in deze kamer</span></div>
   </section>

   <section class="measure-card">
    <div class="measure-card-top">
     <div><span class="measure-reference">{{reference(current)}}</span><h2>{{label(current)}}</h2><p>{{room.floorLabel}} · {{room.label}}</p></div>
     <span class="measure-counter">{{itemIndex+1}} / {{roomItems.length}} open</span>
    </div>
    <div class="measure-state-row">
     <span v-if="skipped.has(reference(current))" class="skipped"><SkipForward :size="15"/>Eerder overgeslagen</span>
     <span v-else>Nog niet gemeten</span>
    </div>

    <div v-if="roomMapPolygons.length" class="measure-room-map">
     <div class="measure-map-caption"><span>{{room.label}}</span><strong><i/> {{reference(current)}} is het oranje meubel</strong></div>
     <svg :viewBox="roomMapViewBox" preserveAspectRatio="xMidYMid meet" role="img" :aria-label="`${room.label}: ${reference(current)} ${label(current)} gemarkeerd`">
      <polygon v-for="(polygon,index) in roomMapPolygons" :key="index" class="measure-map-room" :points="polygon.map(p=>p.join(',')).join(' ')"/>
      <polygon v-for="item in activeRoomAllItems" :key="reference(item)" class="measure-map-furniture" :class="{current:reference(item)===reference(current)}" :points="footprint(item)"><title>{{reference(item)}} · {{label(item)}}</title></polygon>
     </svg>
    </div>

    <fieldset class="measure-fields" :disabled="saving">
     <label><span>Breedte</span><div><input v-model="form.width" inputmode="decimal" type="number" min="5" max="1100" step="0.1" autocomplete="off" @focus="clearField('width')"/><b>cm</b></div></label>
     <label><span>Diepte</span><div><input v-model="form.depth" inputmode="decimal" type="number" min="1" max="550" step="0.1" autocomplete="off" @focus="clearField('depth')"/><b>cm</b></div></label>
     <label><span>Hoogte</span><div><input v-model="form.height" inputmode="decimal" type="number" min="1" max="400" step="0.1" autocomplete="off" @focus="clearField('height')"/><b>cm</b></div></label>
    </fieldset>

    <p class="measure-hint">Meet breedte × diepte × hoogte. Tik een veld aan: de oude waarde wordt meteen gewist zodat je direct kunt typen of dicteren.</p>
    <button class="measure-primary" :disabled="saving" @click="saveAndNext"><Check :size="20"/>{{saving?'Opslaan…':'Opslaan & volgende'}}</button>
    <button class="measure-skip" :disabled="saving" @click="skip"><SkipForward :size="18"/>Overslaan</button>
   </section>

   <nav class="measure-nav" aria-label="Meubelnavigatie">
    <button :disabled="rooms.findIndex(r=>r.key===roomKey)===0&&itemIndex===0" @click="previous"><ChevronLeft :size="20"/>Vorige</button>
    <span>{{roomProcessed}} / {{activeRoomAllItems.length}} verwerkt</span>
    <button @click="next">Volgende<ChevronRight :size="20"/></button>
   </nav>
  </template>

  <section v-else class="measure-card measure-loading"><House :size="24"/>Geen ongemeten meubels gevonden.</section>

  <section v-if="!loading && allItems.length" class="measure-progress-card measure-progress-bottom">
   <div class="measure-progress-copy"><strong>{{measuredCount}} gemeten</strong><span v-if="skippedCount">· {{skippedCount}} overgeslagen</span><span>· {{allItems.length}} totaal</span></div>
   <div class="measure-progress-track"><i :style="{width:`${progress}%`}"/></div>
   <small>{{progress}}% van deze meetronde verwerkt</small>
  </section>
 </main>
</div>
</template>
