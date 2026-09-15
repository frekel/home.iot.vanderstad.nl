<script setup lang="ts">
import {computed,ref,onMounted,onBeforeUnmount} from 'vue';
import {House, Layers, Moon, Lightbulb, Thermometer, Droplets, Zap, Settings, RotateCcw, MoveUpRight, ChevronRight, WifiOff, X, DoorOpen} from '@lucide/vue';
import HouseScene from './components/HouseScene.vue';
import house from './house.json';
type Measurement={value:unknown;units:string|null;settable:boolean;updated_at:number|string|null};
type Device={id:string;name:string;zone_name?:string;class:string;available:boolean|null;measurements:Record<string,Measurement>};
type RoomLink={light:Device|null;climate:Device|null;light_configured:boolean;climate_configured:boolean};
type Zone={name:string;parent:string|null};
type Layout={floors:Record<string,string>;rooms:Record<string,string>;aliases?:Record<string,string>};
type Connection={zones?:Record<string,Zone>;layout?:Layout;connected:boolean;message:string;devices:Device[];rooms:Record<string,RoomLink>;fetched_at?:string};
const floor=ref('ground'),selected=ref('living'),settings=ref(false),scene=ref<InstanceType<typeof HouseScene>>();
const previewLights=ref<Record<string,boolean>>({kitchen:true,dining:true,hall:true,bedroom1:true});
const connection=ref<Connection>({connected:false,message:'Checking Homey…',devices:[],rooms:{}});
const checking=ref(false),commandBusy=ref(false),commandError=ref('');
const pending=ref<{room:string;value:boolean;deadline:number}|null>(null);
const clock=ref(Date.now());let timer:ReturnType<typeof setTimeout>|undefined;let disposed=false;let request:Promise<void>|null=null;
const lights=computed(()=>{const result={...previewLights.value};for(const [id,link] of Object.entries(connection.value.rooms)){if(link.light_configured)result[id]=connection.value.connected&&link.light?.available===true&&link.light.measurements.onoff?.value===true}return result});
const zoneNames=ref<Record<string,Zone>>({});
const layout=ref<Layout>({floors:{},rooms:{}});
function roomLabel(id:string){const original=house.floors.flatMap(f=>f.rooms).find(r=>r.id===id);return zoneNames.value[layout.value.rooms[id]??'']?.name??original?.label??id}
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
const link=computed(()=>connection.value.rooms[selected.value]);
const liveSelected=computed(()=>!!link.value?.light_configured);
const canControl=computed(()=>!liveSelected.value || (connection.value.connected&&link.value?.light?.available===true&&link.value.light.measurements.onoff?.settable===true&&typeof link.value.light.measurements.onoff.value==='boolean'));
const active=computed(()=>rooms.value.filter(r=>lights.value[r.id]).length);
const climate=computed(()=>connection.value.rooms.living?.climate);
function reading(capability:string){const d=climate.value;if(!connection.value.connected||d?.available!==true)return '—';const v=d.measurements[capability]?.value;return typeof v==='number'?v.toFixed(capability==='measure_temperature'?1:0):'—'}
function readingAge(capability:string){const raw=climate.value?.measurements[capability]?.updated_at;if(!raw)return 'Reading time unavailable';const date=typeof raw==='number'?raw:Date.parse(raw);if(!Number.isFinite(date))return 'Reading time unavailable';const minutes=Math.max(0,Math.floor((clock.value-date)/60000));return minutes<1?'Updated just now':`Updated ${minutes} min ago`}
function checkConnection():Promise<void>{
 if(request)return request;
 checking.value=true;
 request=(async()=>{try{const r=await fetch('/dashboard/status',{headers:{Accept:'application/json'},signal:AbortSignal.timeout(12000)});const data=await r.json();if(!data.rooms)throw new Error();if(!disposed){connection.value=data;if(data.layout)layout.value=data.layout;if(data.zones&&Object.keys(data.zones).length)zoneNames.value=data.zones}}catch{if(!disposed)connection.value={...connection.value,connected:false,message:'Connection unavailable. Live controls are disabled.',devices:[]}}finally{
 checking.value=false;request=null;clock.value=Date.now();
 if(pending.value){const p=pending.value;const d=connection.value.rooms[p.room]?.light;if(connection.value.connected&&d?.available===true&&d.measurements.onoff?.value===p.value){pending.value=null}else if(clock.value>p.deadline){commandError.value='Homey has not confirmed the requested state. Check the lamp before retrying.';pending.value=null}}
 }})();return request;
}
async function poll(){await checkConnection();if(!disposed)timer=setTimeout(poll,5000)}
onMounted(poll);onBeforeUnmount(()=>{disposed=true;clearTimeout(timer)});
const values=[5.2,4.8,6.1,4.2,5.7,7.4,5.9];
function chooseFloor(id:string){floor.value=id;selected.value=floors.value.find(f=>f.id===id)!.rooms[0]!.id}
async function toggle(id:string){
 const mapped=connection.value.rooms[id]?.light_configured;
 if(!mapped){previewLights.value[id]=!previewLights.value[id];return}
 if(!canControl.value||commandBusy.value||pending.value)return;
 commandBusy.value=true;commandError.value='';
 try{
  await checkConnection();const device=connection.value.rooms[id]?.light;
  if(!connection.value.connected||device?.available!==true||typeof device.measurements.onoff?.value!=='boolean')throw new Error('The light is unavailable.');
  const desired=!device.measurements.onoff.value;
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
  const r=await fetch(`/dashboard/rooms/${encodeURIComponent(id)}/light`,{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({value:desired}),signal:AbortSignal.timeout(15000)});
  if(!r.ok)throw new Error('The light command could not be confirmed. Check the lamp before retrying.');
  pending.value={room:id,value:desired,deadline:Date.now()+15000};await checkConnection();
 }catch(e){commandError.value=e instanceof Error?e.message:'Light command failed.';await checkConnection()}finally{commandBusy.value=false}
}
function allOff(){for(const r of rooms.value)if(!connection.value.rooms[r.id]?.light_configured)previewLights.value[r.id]=false}
</script>
<template>
<div class="dashboard">
<header><a class="brand" href="/"><span class="brand-icon"><House :size="23"/></span><span>Van der Stad<span class="brand-sub">HOME / ZUIDGORS 20</span></span></a><nav class="floor-nav" aria-label="Floors"><button v-for="f in floors" :key="f.id" :class="{active:floor===f.id}" @click="chooseFloor(f.id)"><Layers :size="15"/>{{f.label}}</button></nav><div class="header-right"><span class="demo-badge"><span/>{{connection.connected ? 'HOMEY CONNECTED' : 'HOMEY OFFLINE'}}</span><button class="icon-button" aria-label="Connection settings" @click="settings=true"><Settings :size="19"/></button></div></header>
<main>
<aside class="sidebar">
<div class="eyebrow">YOUR HOME, AT A GLANCE</div><h1>A little closer<br>to home<span>.</span></h1><p class="muted intro">{{current.label}} <span> / </span> 5.5 × {{current.length}} m</p>
<section class="side-section"><div class="section-title"><Zap :size="15"/><span>DAILY ENERGY</span><small>Sample</small></div><div class="energy-total">5.9 <span>kWh</span><small>Today</small></div><div class="mini-bars"><div v-for="(v,i) in values" :key="i"><span>{{v}}</span><i :style="{height:`${v*7}px`}" :class="{highlight:i===6}"/><small>{{['M','T','W','T','F','S','S'][i]}}</small></div></div></section>
<section class="side-section rooms"><div class="section-title"><House :size="15"/><span>HOMEY ROOMS</span><small>{{rooms.length}}</small></div><button v-for="r in rooms" :key="r.id" class="room-row" :class="{selected:selected===r.id}" @click="selected=r.id"><span class="room-dot" :class="{on:lights[r.id]}"/><span>{{r.label}}</span><ChevronRight :size="14"/></button></section>
<div class="side-bottom"><WifiOff :size="16"/><div>{{connection.connected ? 'Homey connected' : 'Homey not connected'}}<small>Live devices refresh every 5 seconds</small></div></div>
</aside>
<section class="house-view"><div class="view-heading"><span class="eyebrow">EXPLORE YOUR SPACE</span><h2>{{current.label}}</h2><p>Drag to rotate · Scroll to zoom · Click a room</p></div><div class="view-controls"><button class="icon-button" aria-label="Reset camera" @click="scene?.reset()"><RotateCcw :size="17"/></button><button class="icon-button" aria-label="Top view" @click="scene?.top()"><Layers :size="17"/></button></div><HouseScene ref="scene" :floor="floor" :selected="selected" :lights="lights" :aliases="layout.aliases ?? {}" @select="selected=$event"/><div class="model-note">APPROXIMATE MODEL <span>·</span> Internal dimensions in metres</div><div class="room-control" v-if="room"><span class="control-icon"><Lightbulb :size="21"/></span><div><small>{{liveSelected ? 'LIVE LIGHT' : 'PREVIEW LIGHT'}}</small><strong>{{liveSelected ? (link?.light?.name ?? 'Linked light unavailable') : room.label}}</strong></div><button class="toggle" :class="{on:lights[selected]}" role="switch" :aria-checked="!!lights[selected]" :disabled="!canControl || commandBusy || !!pending" :aria-label="liveSelected ? `Live light: ${link?.light?.name ?? room.label}` : `Preview light in ${room.label}`" @click="toggle(selected)"><span/></button></div><p class="command-notice" role="status" v-if="commandError || pending || commandBusy">{{commandError || (commandBusy ? 'Sending command…' : 'Waiting for Homey to confirm…')}}</p></section>
<aside class="rightbar"><div class="eyebrow">SET THE MOOD</div><h3>Home controls</h3><button class="scene-card" @click="allOff"><Moon :size="22"/><strong>Preview lights out</strong><span>Only unlinked sample lights<MoveUpRight :size="15"/></span></button><div class="light-summary"><span>{{active}}<small>/ {{rooms.length}}</small></span><p>room lights on · live + preview</p></div><div class="access"><div class="section-title"><DoorOpen :size="15"/>HOME ACCESS</div><div><span>Front door</span><strong>—</strong></div><p>No door sensor linked yet.</p></div><div class="connection-card"><span class="connection-orb"><House :size="24"/></span><h3>{{connection.connected ? 'Homey connected' : 'Homey offline'}}</h3><p>Living-room climate and the linked light use Homey. Other controls remain previews.</p><button @click="settings=true">Connection details <ChevronRight :size="15"/></button></div></aside>
</main>
<footer class="metrics"><section class="metric energy-chart"><div class="section-title"><Zap :size="15"/>ENERGY CONSUMPTION <small>Sample week · kWh</small></div><div class="week-chart"><div v-for="(v,i) in values" :key="i"><span>{{v}}</span><i :style="{height:`${v*9}px`}" :class="{highlight:i===6}"/><small>{{['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][i]}}</small></div></div></section><section class="metric climate"><div class="metric-top"><span class="eyebrow">{{roomLabel('living')}} <small>{{connection.connected ? 'Homey' : 'Offline'}}</small></span><Thermometer :size="20"/></div><div class="reading">{{reading('measure_temperature')}}<span>°C</span></div><p>{{climate?.name ?? 'No linked sensor'}} · {{readingAge('measure_temperature')}}</p></section><section class="metric climate"><div class="metric-top"><span class="eyebrow">{{roomLabel('living')}} · humidity <small>{{connection.connected ? 'Homey' : 'Offline'}}</small></span><Droplets :size="20"/></div><div class="reading">{{reading('measure_humidity')}}<span>%</span></div><p>{{climate?.name ?? 'No linked sensor'}} · {{readingAge('measure_humidity')}}</p></section></footer>
</div>
<div v-if="settings" class="modal-backdrop" @click.self="settings=false"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="connection-title"><button class="icon-button close" aria-label="Close connection details" @click="settings=false"><X :size="20"/></button><span class="eyebrow">HOMEY PRO 2023</span><h2 id="connection-title">Connect your home</h2><p>The living-room light controls the linked Homey device. Other rooms are labelled previews.</p><p>Device states refresh every five seconds. Living-room climate comes from the linked sensor; energy charts still show sample data.</p><div class="connection-state"><WifiOff :size="18"/> {{connection.message}}</div><button class="primary" :disabled="checking" @click="checkConnection">{{checking ? 'Checking…' : 'Check connection'}}</button><div class="zone-tree" v-if="zoneTree.length"><h3>Rooms from Homey</h3><p>Names and hierarchy sync from Homey within one minute.</p><div v-for="zone in zoneTree" :key="zone.id" :style="{paddingLeft:`${zone.depth*16}px`}"><Layers :size="13"/>{{zone.name}}</div></div><div class="device-list" v-if="connection.devices.length"><h3>{{connection.devices.length}} live devices · read only</h3><article v-for="device in connection.devices" :key="device.id"><strong>{{device.name}}</strong><small>{{device.zone_name || 'No zone'}} · {{device.available ? 'Available' : 'Unavailable'}}</small><small v-for="(measurement,capability) in device.measurements" :key="capability">{{capability}}: {{measurement.value ?? 'Unknown'}} {{measurement.units}}</small></article></div><button class="primary" @click="settings=false">Back to the house</button></section></div>
</template>
