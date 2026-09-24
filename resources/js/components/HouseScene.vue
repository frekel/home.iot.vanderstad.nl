<script setup lang="ts">
import { onMounted, onBeforeUnmount, watch, ref } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import {house} from '../house';
import modelVersions from '../model-versions.json';
import {furnitureState} from '../furniture';
const props=defineProps<{floor:string; selected:string; lights:Record<string,boolean>; aliases:Record<string,string>}>();
const emit=defineEmits<{select:[id:string];toggleRoom:[id:string]}>();
const host=ref<HTMLDivElement>();const error=ref('');
let renderer:THREE.WebGLRenderer,scene:THREE.Scene,camera:THREE.PerspectiveCamera,controls:OrbitControls,observer:ResizeObserver,model:THREE.Group|undefined,frame=0,version=0;
const pins=new THREE.Group();let down=[0,0];
const cutawayHeight=1.30,fadeStart=1.12;
const furnitureClipPlane=new THREE.Plane(new THREE.Vector3(0,-1,0),cutawayHeight);
function dispose(root:THREE.Object3D){root.traverse(o=>{if(o instanceof THREE.Mesh||o instanceof THREE.LineSegments){o.geometry.dispose();for(const m of Array.isArray(o.material)?o.material:[o.material])m.dispose()}})}
function reset(){camera.position.set(8,11,12);controls.target.set(0,0,0);controls.update()}
function top(){camera.position.set(0,16,.01);controls.target.set(0,0,0);controls.update()}
defineExpose({reset,top});
// Plan X/Y corresponds to Three.js X/-Z; display_mirrored is stored with each floor.
function mirrorPlan(object:THREE.Object3D){const mirrored=house.floors.find(f=>f.id===props.floor)?.display_mirrored??false;object.scale.set(mirrored?-1:1,1,mirrored?1:-1)}
function cutawayMaterial(source:THREE.Material){
 const material=source.clone();
 material.clippingPlanes=[furnitureClipPlane];
 material.clipIntersection=false;
 material.transparent=true;
 material.alphaTest=.015;
 material.depthWrite=true;
 const previous=material.onBeforeCompile;
 material.onBeforeCompile=(shader,renderer)=>{
  previous.call(material,shader,renderer);
  shader.vertexShader=shader.vertexShader
   .replace('#include <common>','#include <common>\nvarying float vFurnitureCutawayY;')
   .replace('#include <worldpos_vertex>','#include <worldpos_vertex>\nvFurnitureCutawayY = (modelMatrix * vec4(transformed, 1.0)).y;');
  shader.fragmentShader=shader.fragmentShader
   .replace('#include <common>','#include <common>\nvarying float vFurnitureCutawayY;')
   .replace('#include <dithering_fragment>',`float furnitureCutawayFade = 1.0 - smoothstep(${fadeStart.toFixed(3)}, ${cutawayHeight.toFixed(3)}, vFurnitureCutawayY);\ngl_FragColor.a *= furnitureCutawayFade;\nif (gl_FragColor.a < 0.015) discard;\n#include <dithering_fragment>`);
 };
 material.customProgramCacheKey=()=>`furniture-cutaway-${fadeStart}-${cutawayHeight}`;
 return material;
}
function applyFurnitureCutaway(mesh:THREE.Mesh){
 mesh.material=Array.isArray(mesh.material)?mesh.material.map(cutawayMaterial):cutawayMaterial(mesh.material);
}
function updatePins(){
 mirrorPlan(pins);
 dispose(pins);pins.clear();const f=house.floors.find(f=>f.id===props.floor)!;
 for(const room of f.rooms){if(room.id.includes('stairs')||props.aliases[room.id])continue;const p=room.polygon;const x=p.reduce((s,p)=>s+p[0]!,0)/p.length;const z=p.reduce((s,p)=>s+p[1]!,0)/p.length;
 const on=!!props.lights[room.id];const material=new THREE.MeshStandardMaterial({color:on?0xff920f:0x7e8c9f,emissive:on?0xff6600:0x000000,emissiveIntensity:1.2,depthTest:false,depthWrite:false});const pin=new THREE.Mesh(new THREE.SphereGeometry(.095,16,16),material);pin.position.set(x,.45,-z);pin.renderOrder=1000;pin.userData.room=room.id;pin.userData.lightPin=true;pins.add(pin);
 if(on){const light=new THREE.PointLight(0xffbd70,4,3,2);light.position.set(x,.9,-z);pins.add(light)}
 }
 if(model)model.traverse(o=>{if(o instanceof THREE.Mesh&&o.name.startsWith('room__')){const m=o.material as THREE.MeshStandardMaterial;m.emissive.set((props.aliases[o.name.slice(6)]??o.name.slice(6))===props.selected?0x604020:0x000000);m.emissiveIntensity=.4}})
}
async function load(){const request=++version;error.value='';try{const gltf=await new GLTFLoader().loadAsync(furnitureState.value?.models[props.floor]??`/models/${props.floor}.glb?v=${modelVersions[props.floor as keyof typeof modelVersions]}`);if(request!==version){dispose(gltf.scene);return}if(model){scene.remove(model);dispose(model)}model=gltf.scene;model.traverse(o=>{if(o instanceof THREE.Mesh&&o.name.includes('__tread_')){const edges=new THREE.LineSegments(new THREE.EdgesGeometry(o.geometry),new THREE.LineBasicMaterial({color:0x594433}));o.add(edges)}if(o instanceof THREE.Mesh&&o.name.startsWith('furniture__'))applyFurnitureCutaway(o)});mirrorPlan(model);scene.add(model);updatePins()}catch{error.value='The 3D model could not be loaded. Try refreshing the page.'}}
function click(e:PointerEvent){if(Math.hypot(e.clientX-down[0]!,e.clientY-down[1]!)>5)return;const r=host.value!.getBoundingClientRect();const ray=new THREE.Raycaster();ray.setFromCamera(new THREE.Vector2((e.clientX-r.left)/r.width*2-1,-(e.clientY-r.top)/r.height*2+1),camera);const pinHit=ray.intersectObjects(pins.children,true).find(hit=>hit.object.userData.lightPin);if(pinHit?.object.userData.room){emit('toggleRoom',pinHit.object.userData.room);return}for(const hit of ray.intersectObjects(model?.children??[],true)){const id=hit.object.name.startsWith('room__')?hit.object.name.slice(6):null;if(id){emit('select',props.aliases[id]??id);break}}}
onMounted(()=>{try{renderer=new THREE.WebGLRenderer({antialias:true,alpha:true});renderer.localClippingEnabled=true;renderer.setPixelRatio(Math.min(devicePixelRatio,2));renderer.setClearColor(0x000000,0);renderer.toneMapping=THREE.ACESFilmicToneMapping;renderer.toneMappingExposure=1.35;host.value!.append(renderer.domElement);scene=new THREE.Scene();camera=new THREE.PerspectiveCamera(38,1,.1,100);controls=new OrbitControls(camera,renderer.domElement);controls.enableDamping=true;controls.minDistance=5;controls.maxDistance=28;controls.maxPolarAngle=Math.PI/2-.08;reset();scene.add(new THREE.HemisphereLight(0xdce8ff,0x777062,3));const sun=new THREE.DirectionalLight(0xffeed5,3);sun.position.set(3,10,5);scene.add(sun);const grid=new THREE.GridHelper(50,100,0x2c343e,0x1d242d);grid.position.y=-.18;scene.add(grid,pins);observer=new ResizeObserver(()=>{const {width,height}=host.value!.getBoundingClientRect();renderer.setSize(width,height);camera.aspect=width/height;camera.updateProjectionMatrix()});observer.observe(host.value!);renderer.domElement.addEventListener('pointerdown',e=>{down=[e.clientX,e.clientY]});renderer.domElement.addEventListener('pointerup',click);const animate=()=>{frame=requestAnimationFrame(animate);controls.update();renderer.render(scene,camera)};animate();load()}catch{error.value='This browser could not start WebGL. Enable hardware acceleration to view the house.'}});
watch([()=>props.floor,()=>furnitureState.value?.models[props.floor]],()=>{if(scene)load()});watch(()=>[props.selected,props.lights,props.aliases],()=>{if(scene)updatePins()},{deep:true});
onBeforeUnmount(()=>{version++;cancelAnimationFrame(frame);observer?.disconnect();controls?.dispose();if(model)dispose(model);dispose(pins);renderer?.dispose()});
</script>
<template><div class="house-canvas" ref="host"><div v-if="error" class="scene-error">{{error}}</div></div></template>