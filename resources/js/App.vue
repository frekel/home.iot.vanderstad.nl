<script setup lang="ts">
import {startFurniturePolling,stopFurniturePolling} from './furniture';
import {onBeforeUnmount as onFurnitureUnmount,onMounted as onFurnitureMounted} from 'vue';
onFurnitureMounted(startFurniturePolling);onFurnitureUnmount(stopFurniturePolling);
import {computed,ref,onMounted,onBeforeUnmount} from 'vue';
import {House, Layers, Lightbulb, Thermometer, Droplets, Settings, RotateCcw, ChevronRight, WifiOff, X, DoorOpen} from '@lucide/vue';
import HouseScene from './components/HouseScene.vue';
import {house} from './house';
import FurnitureCatalogue from './components/FurnitureCatalogue.vue';
import EnergyPanel, {type Energy} from './components/EnergyPanel.vue';
import LightControls from './components/LightControls.vue';
type Measurement={value:unknown;units:string|null;settable:boolean;updated_at:number|string|null};
type Device={id:string;name:string;zone:string;zone_name?:string;class:string;available:boolean|null;measurements:Record<string,Measurement>};
type RoomLink={light:Device|null;climate:Device|null;light_configured:boolean;climate_configured:boolean};
type Zone={name:string;parent:string|null};
type Layout={floors:Record<string,string>;rooms:Record<string,string>;aliases?:Record<string,string>};
type Access={id:string;name:string;zone:string;available:boolean;open:boolean|null;locked:boolean|null;contact_updated_at:number|string|null;lock_updated_at:number|string|null};
type Connection={access?:Access[];zones?:Record<string,Zone>;layout?:Layout;connected:boolean;message:string;devices:Device[];rooms:Record<string,RoomLink>;fetched_at?:string};
const floor=ref('ground'),selected=ref('living'),settings=ref(false),scene=ref<InstanceType<typeof HouseScene>>();
const showFurniture=ref(new URLSearchParams(location.search).has('meubels'));
const showLights=ref(false);
const knownLights=ref<Device[]>([]);
const allLights=computed(()=>knownLights.value.slice().sort((a,b)=>(a.zone_name??'').localeCompare(b.zone_name??'','nl') || a.name.localeCompare(b.name,'nl')));
const selectedLights=computed(()=>allLights.value.filter(d=>d.zone===layout.value.rooms[selected.value]));
const connection=ref<Connection>({connected:false,message:'Checking Homey…',devices:[],rooms:{}});
const checking=ref(false);
const commandBusy=ref<Record<string,boolean>>({}),commandError=ref<Record<string,string>>({});
const pending=ref<Record<string,{value:boolean;deadline:number}>>({});
const clock=ref(Date.now());let timer:ReturnType<typeof setTimeout>|undefined;let disposed=false;let request:Promise<void>|null=null;
const lights=computed(()=>Object.fromEntries(Object.entries(layout.value.rooms).map(([room,zone])=>[room,connection.value.connected&&allLights.value.some(d=>d.zone===zone&&d.available===true&&d.measurements.onoff?.value===true)])));
const zoneNames=ref<Record<string,Zone>>({});
const layout=ref<Layout>({floors:{},rooms:{}});
function roomLabel(id:string){const original=house.floors.flatMap(f=>f.rooms).find(r=>r.id===id);return zoneNames.value[layout.value.rooms[id]??'']?.name??original?.label??id}
function furnitureRoomLabel(id:string){return roomLabel(layout.value.aliases?.[id]??id)}
const floors=computed(()=>house.floors.map(f=>({...f,label:zoneNames.value[layout.value.floors[f.id]??'']?.name??f.label,rooms:f.rooms.filter(r=>!layout.value.aliases?.[r.id]).map(r=>({...r,label:roomLabel(r.id)}))})));
const current=computed(()=>floors.value.find(f=>f.id===floor.value)!);
const zoneTree=computed(()=>{
 const result:{id:string;name:string;depth:number}[]=[];const visited=new Set<string>();
 function visit(id:string,depth:number){if(visited.has(id))return;visited.add(id);const zone=zoneNames.value[id]!;result.push({id,name:zone.name,depth});for(const [child] of Object.entries(zoneNames.value).filter(([,z])=>z.parent===id).sort((a,b)=>a[1].name.localeCompare(b[1].name,'nl')))visit(child,depth+1)}
 for(const [id,z] of Object.entries(zoneNames.value))if(!z.parent||!zoneNames.value[z.parent])visit(id,0);
 for(const id of Object.keys(zoneNames.value))if(!visited.has(id))visit(id,0);
 return result;
});
const rooms=computed(()=>current.value.rooms.filter(r=>!r.id.includes('stairs')));
const room=computed(()=>current.value.rooms.find(r=>r.id===selected.value));
const active=computed(()=>rooms.value.filter(r=>lights.value[r.id]).length);
const climate=computed(()=>connection.value.rooms.living?.climate);
function accessChanged(value:number|string|null){if(value===null)return 'Wijzigingstijd onbekend';const date=new Date(value);return Number.isNaN(date.getTime())?'Wijzigingstijd onbekend':`Gewijzigd ${date.toLocaleString('nl-NL',{timeZone:'Europe/Amsterdam',day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'})}`}
function reading(capability:string){const d=climate.value;if(!connection.value.connected||d?.available!==true)return '—';const v=d.measurements[capability]?.value;return typeof v==='number'?v.toFixed(capability==='measure_temperature'?1:0):'—'}
function readingAge(capability:string){const raw=climate.value?.measurements[capability]?.updated_at;if(!raw)return 'Reading time unavailable';const date=typeof raw==='number'?raw:Date.parse(raw);if(!Number.isFinite(date))return 'Reading time unavailable';const minutes=Math.max(0,Math.floor((clock.value-date)/60000));return minutes<1?'Updated just now':`Updated ${minutes} min ago`}
function checkConnection():Promise<void>{
 if(request)return request;
 checking.value=true;
 request=(async()=>{try{const r=await fetch('/dashboard/status',{headers:{Accept:'application/json'},signal:AbortSignal.timeout(12000)});const data=await r.json();if(!data.rooms)throw new Error();if(!disposed){connection.value=data;if(data.connected)knownLights.value=data.devices.filter((d:Device)=>d.class==='light');if(data.layout)layout.value=data.layout;if(data.zones&&Object.keys(data.zones).length)zoneNames.value=data.zones}}catch{if(!disposed)connection.value={...connection.value,connected:false,message:'Connection unavailable. Live controls are disabled.',devices:[]}}finally{
 checking.value=false;request=null;clock.value=Date.now();
 for(const [id,p] of Object.entries(pending.value)){const d=connection.value.devices.find(d=>d.id===id);if(connection.value.connected&&d?.available===true&&d.measurements.onoff?.value===p.value){delete pending.value[id]}else if(clock.value>p.deadline){commandError.value[id]='Homey has not confirmed the state. Check the lamp before retrying.';delete pending.value[id]}}

 }})();return request;
}
async function poll(){await checkConnection();if(!disposed)timer=setTimeout(poll,5000)}
onMounted(poll);onBeforeUnmount(()=>{disposed=true;clearTimeout(timer)});
const energy=ref<Energy|null>(null);let energyTimer:ReturnType<typeof setTimeout>|undefined;
async function refreshEnergy(){
 try{const response=await fetch('/dashboard/energy',{headers:{Accept:'application/json'},signal:AbortSignal.timeout(15000)});const data=await response.json();if(!disposed)energy.value=data}
 catch{if(!disposed)energy.value={available:false,date:'',week:'',timezone:'Europe/Amsterdam',today:null,days:{},updated_at:null}}
 if(!disposed)energyTimer=setTimeout(refreshEnergy,60000);
}
onMounted(refreshEnergy);onBeforeUnmount(()=>clearTimeout(energyTimer));
function chooseFloor(id:string){floor.value=id;selected.value=floors.value.find(f=>f.id===id)!.rooms[0]!.id}
async function toggle(id:string){
 if(commandBusy.value[id]||pending.value[id]||!connection.value.connected)return;
 commandBusy.value[id]=true;commandError.value[id]='';
 try{
  await checkConnection();const device=connection.value.devices.find(d=>d.id===id);
  if(!connection.value.connected||device?.class!=='light'||device.available!==true||device.measurements.onoff?.settable!==true||typeof device.measurements.onoff.value!=='boolean')throw new Error('The light is unavailable.');
  const desired=!device.measurements.onoff.value;
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
  const r=await fetch(`/dashboard/lights/${encodeURIComponent(id)}`,{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({value:desired}),signal:AbortSignal.timeout(15000)});
  if(!r.ok)throw new Error('The command could not be confirmed. Check the lamp before retrying.');
  pending.value[id]={value:desired,deadline:Date.now()+15000};
  if(request)await request;
  await checkConnection();
 }catch(e){commandError.value[id]=e instanceof Error?e.message:'Light command failed.';await checkConnection()}finally{commandBusy.value[id]=false}
}
async function toggleRoom(id:string){
 if(!connection.value.connected)return;
 await checkConnection();
 const roomId=layout.value.aliases?.[id]??id;
 const zone=layout.value.rooms[roomId];
 if(!zone)return;
 const targets=allLights.value.filter(d=>d.zone===zone&&d.available===true&&d.measurements.onoff?.settable===true&&typeof d.measurements.onoff?.value==='boolean');
 if(!targets.length||targets.some(d=>commandBusy.value[d.id]||pending.value[d.id]))return;
 const desired=!targets.some(d=>d.measurements.onoff?.value===true);
 const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
 for(const d of targets){commandBusy.value[d.id]=true;commandError.value[d.id]=''}
 try{
  const responses=await Promise.all(targets.map(d=>fetch(`/dashboard/lights/${encodeURIComponent(d.id)}`,{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({value:desired}),signal:AbortSignal.timeout(15000)})));
  if(responses.some(r=>!r.ok))throw new Error('Niet alle lampen accepteerden het commando.');
  for(const d of targets)pending.value[d.id]={value:desired,deadline:Date.now()+15000};
  await checkConnection();
 }catch(e){for(const d of targets)commandError.value[d.id]=e instanceof Error?e.message:'Lichtcommando mislukt.';await checkConnection()}finally{for(const d of targets)commandBusy.value[d.id]=false}
}
</script>
<template>
<div class="dashboard">
<header><a class="brand" href="/"><span class="brand-icon"><House :size="23"/></span><span>Van der Stad<span class="brand-sub">HOME / ZUIDGORS 20</span></span></a><nav class="floor-nav" aria-label="Floors"><button v-for="f in floors" :key="f.id" :class="{active:floor===f.id}" @click="chooseFloor(f.id)"><Layers :size="15"/>{{f.label}}</button></nav><div class="header-right"><button class="lights-button" @click="showFurniture=true">Meubels</button><button class="lights-button" @click="showLights=true"><Lightbulb :size="16"/>All lights ({{allLights.length}})</button><span class="demo-badge"><span/>{{connection.connected ? 'HOMEY CONNECTED' : 'HOMEY OFFLINE'}}</span><button class="icon-button" aria-label="Connection settings" @click="settings=true"><Settings :size="19"/></button></div></header>
<main>
<aside class="sidebar">
<p class="muted intro">{{current.label}} <span> / </span> {{current.width}} × {{current.length}} m</p>
<EnergyPanel :energy="energy" compact/>
<section class="side-section rooms"><div class="section-title"><House :size="15"/><span>HOMEY ROOMS</span><small>{{rooms.length}}</small></div><button v-for="r in rooms" :key="r.id" class="room-row" :class="{selected:selected===r.id}" @click="selected=r.id"><span class="room-dot" :class="{on:lights[r.id]}"/><span>{{r.label}}</span><ChevronRight :size="14"/></button></section>
<div class="side-bottom"><WifiOff :size="16"/><div>{{connection.connected ? 'Homey connected' : 'Homey not connected'}}<small>Live devices refresh every 5 seconds</small></div></div>
</aside>
<section class="house-view"><div class="view-heading"><span class="eyebrow">EXPLORE YOUR SPACE</span><h2>{{current.label}}</h2><p>Drag to rotate · Scroll to zoom · Click a room</p></div><div class="view-controls"><button class="icon-button" aria-label="Reset camera" @click="scene?.reset()"><RotateCcw :size="17"/></button><button class="icon-button" aria-label="Top view" @click="scene?.top()"><Layers :size="17"/></button></div><HouseScene ref="scene" :floor="floor" :selected="selected" :lights="lights" :aliases="layout.aliases ?? {}" @select="selected=$event" @toggle-room="toggleRoom"/><div class="model-note">APPROXIMATE MODEL <span>·</span> Internal dimensions in metres</div><div class="room-lights" v-if="room"><div class="section-title"><Lightbulb :size="15"/>{{room.label}} · {{selectedLights.length}} lights</div><LightControls :devices="selectedLights" :connected="connection.connected" :busy="commandBusy" :pending="pending" :errors="commandError" @toggle="toggle"/></div></section>
</main>
<footer class="metrics dashboard-metrics">
<EnergyPanel :energy="energy"/>
<section class="metric climate"><div class="metric-top"><span class="eyebrow">{{roomLabel('living')}} <small>{{connection.connected ? 'Homey' : 'Offline'}}</small></span><Thermometer :size="20"/></div><div class="reading">{{reading('measure_temperature')}}<span>°C</span></div><p>{{climate?.name ?? 'No linked sensor'}} · {{readingAge('measure_temperature')}}</p></section>
<section class="metric climate"><div class="metric-top"><span class="eyebrow">{{roomLabel('living')}} · humidity <small>{{connection.connected ? 'Homey' : 'Offline'}}</small></span><Droplets :size="20"/></div><div class="reading">{{reading('measure_humidity')}}<span>%</span></div><p>{{climate?.name ?? 'No linked sensor'}} · {{readingAge('measure_humidity')}}</p></section>
<section class="metric footer-lights"><button type="button" class="footer-lights-button" @click="showLights=true"><div class="metric-top"><span class="eyebrow">VERLICHTING <small>Homey</small></span><Lightbulb :size="20"/></div><div class="footer-lights-count">{{active}}<span>/ {{rooms.length}}</span></div><strong>All lights</strong><p>{{allLights.length}} Homey lights · {{active}} kamers aan</p></button></section>
<section class="metric footer-access"><div class="metric-top"><span class="eyebrow">HOME ACCESS <small>Homey</small></span><DoorOpen :size="20"/></div><div class="footer-access-list"><article v-for="door in connection.access ?? []" :key="door.id"><div class="footer-access-title"><strong>{{door.name}}</strong><small>{{door.zone}}</small></div><template v-if="connection.connected && door.available"><div class="footer-access-state"><b :class="door.open===true?'access-open':door.open===false?'access-closed':''">{{door.open===true?'Open':door.open===false?'Dicht':'Deur ?'}}</b><b :class="door.locked===true?'access-closed':door.locked===false?'access-open':''">{{door.locked===true?'Op slot':door.locked===false?'Niet op slot':'Slot ?'}}</b></div><small class="footer-access-time">{{accessChanged(door.contact_updated_at)}}</small></template><small v-else class="footer-access-time">Status niet beschikbaar</small></article></div><p class="footer-access-sync">Homey · elke 5 seconden</p></section>
</footer>
</div>
<div v-if="settings" class="modal-backdrop" @click.self="settings=false"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="connection-title"><button class="icon-button close" aria-label="Close connection details" @click="settings=false"><X :size="20"/></button><span class="eyebrow">HOMEY PRO 2023</span><h2 id="connection-title">Connect your home</h2><p>Control every Homey light from its room or the All lights view.</p><p>Device states refresh every five seconds. Living-room climate comes from the linked sensor; energy reports come directly from Homey Energy and refresh every minute.</p><div class="connection-state"><WifiOff :size="18"/> {{connection.message}}</div><button class="primary" :disabled="checking" @click="checkConnection">{{checking ? 'Checking…' : 'Check connection'}}</button><div class="zone-tree" v-if="zoneTree.length"><h3>Rooms from Homey</h3><p>Names and hierarchy sync from Homey within one minute.</p><div v-for="zone in zoneTree" :key="zone.id" :style="{paddingLeft:`${zone.depth*16}px`}"><Layers :size="13"/>{{zone.name}}</div></div><div class="device-list" v-if="connection.devices.length"><h3>{{connection.devices.length}} live devices</h3><article v-for="device in connection.devices" :key="device.id"><strong>{{device.name}}</strong><small>{{device.zone_name || 'No zone'}} · {{device.available ? 'Available' : 'Unavailable'}}</small><small v-for="(measurement,capability) in device.measurements" :key="capability">{{capability}}: {{measurement.value ?? 'Unknown'}} {{measurement.units}}</small></article></div><button class="primary" @click="settings=false">Back to the house</button></section></div>
<div v-if="showLights" class="modal-backdrop" @click.self="showLights=false"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="lights-title"><button class="icon-button close" aria-label="Close lights" @click="showLights=false"><X :size="20"/></button><span class="eyebrow">HOMEY LIGHTS</span><h2 id="lights-title">All lights</h2><p>Switch lights individually. States refresh from Homey every five seconds.</p><LightControls :devices="allLights" :connected="connection.connected" :busy="commandBusy" :pending="pending" :errors="commandError" @toggle="toggle"/></section></div>
<FurnitureCatalogue v-if="showFurniture" :initial-floor="floor" :room-label="furnitureRoomLabel" @close="showFurniture=false"/>
</template>

<style>
@media (min-width:1151px){
 .dashboard>main{grid-template-columns:220px minmax(0,1fr)}
 .dashboard .sidebar{padding-right:18px}
 .dashboard .house-canvas{transform:translateX(7%)}
 .dashboard-metrics{grid-template-columns:minmax(225px,1.08fr) minmax(150px,.72fr) minmax(150px,.72fr) minmax(145px,.64fr) minmax(230px,1fr);gap:10px}
}
.dashboard-metrics .metric{padding:16px 17px}
.dashboard-metrics .reading{font-size:40px;margin-top:10px}
.dashboard-metrics .reading>span{font-size:24px}
.footer-lights{padding:0!important}
.footer-lights-button{width:100%;height:100%;padding:16px 17px;background:transparent;text-align:left;border-radius:14px}
.footer-lights-button .metric-top svg,.footer-access .metric-top svg{color:var(--orange)}
.footer-lights-count{font-size:38px;font-weight:350;letter-spacing:-1.5px;margin:14px 0 2px}
.footer-lights-count span{font-size:16px;color:#657180;margin-left:5px;letter-spacing:0}
.footer-lights-button strong{display:block;font-size:12px;font-weight:550;margin-top:2px}
.footer-lights-button p{font-size:9px;color:var(--muted);margin:6px 0 0}
.footer-access{display:flex;flex-direction:column;min-width:0}
.footer-access-list{display:grid;gap:7px;margin-top:10px;overflow:auto;min-height:0}
.footer-access-list article{padding-bottom:6px;border-bottom:1px solid var(--line)}
.footer-access-title{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.footer-access-title strong{font-size:10px;font-weight:550;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.footer-access-title small{font-size:8px;color:var(--muted);white-space:nowrap}
.footer-access-state{display:flex;gap:10px;margin-top:4px;font-size:9px}
.footer-access-state b{font-weight:500}
.footer-access-time{display:block;margin-top:3px;font-size:7px;color:#596675;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.footer-access-sync{font-size:7px;color:#596675;margin:auto 0 0;padding-top:5px}
@media (min-width:751px) and (max-width:1150px){
 .dashboard-metrics{grid-template-columns:repeat(3,minmax(0,1fr));height:auto}
 .dashboard-metrics .metric{height:175px}
}
</style>