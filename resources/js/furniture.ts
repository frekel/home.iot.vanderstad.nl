import {ref} from 'vue';

export type FurnitureItem = {
 id:string; floor:string; kind:string; x:number; y:number; rotation:number;
 width:number; depth:number; height:number; base_z:number; color?:string;
 group?:string; group_name?:string;
};
export type FurnitureState = {
 revision:number; model_revision:number|null; status:'ready'|'queued'|'building'|'failed';
 items:FurnitureItem[]; models:Record<string,string>;
};
export const furnitureState=ref<FurnitureState>();
export const furnitureLoadError=ref('');
let timer:ReturnType<typeof setTimeout>|undefined;
let stopped=false;
export async function refreshFurniture(){
 const response=await fetch('/dashboard/furniture',{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(15000)});
 if(!response.ok)throw new Error('De meubelgegevens konden niet worden geladen.');
 const next=await response.json() as FurnitureState;
 // A poll can start just before a save and finish afterwards. Never let that
 // older response replace the newer revision returned by the save request.
 if(!furnitureState.value||next.revision>=furnitureState.value.revision)furnitureState.value=next;
 furnitureLoadError.value='';
}
export function startFurniturePolling(){
 stopped=false;
 const poll=async()=>{try{await refreshFurniture()}catch{furnitureLoadError.value='Meubelgegevens tijdelijk niet bereikbaar.'}finally{if(!stopped)timer=setTimeout(poll,4000)}};
 void poll();
}
export function stopFurniturePolling(){stopped=true;clearTimeout(timer)}
