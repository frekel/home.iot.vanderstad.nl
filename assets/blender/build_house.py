"""Rebuild cutaway GLBs and editable .blend from traced reference geometry.
Run from repository root: blender --background --python assets/blender/build_house.py
Coordinates are metres. Plan tracing is approximate; shell dimensions are user supplied.
"""
import bpy, json, math, runpy, hashlib, os
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUTPUT=Path(os.environ.get('FURNITURE_OUTPUT',str(ROOT/'public/models')))
OUTPUT.mkdir(parents=True,exist_ok=True)
data=json.loads((ROOT/'resources/js/house.json').read_text())
# User-measured correction: opposite the first-floor stairs the wall between
# the two bedroom doors is 63 cm wide. Preserve its original centre point.
upper=next(f for f in data['floors'] if f['id']=='upper')
for wall in upper['walls']:
 if all(abs(a-b)<.0001 for a,b in zip(wall,[-.2326,.2292,.1628,.2292])):
  wall[:]=[-.3499,.2292,.2801,.2292]
  break

# Final measured attic geometry inside the 8.00 x 5.50 m roof shell.
# Longitudinal clear dimensions:
# 0.60 knee-wall zone + 4.40 rooms + 0.20 wall + 2.20 laundry
# + 0.60 knee-wall zone = 8.00 m.
# Across the house: Lily 3.30 + 0.20 wall + closet/Zolder 2.00 = 5.50 m.
# Upper room block: closet 1.90 + 0.20 wall + Zolder 2.30 = 4.40 m.
attic=next(f for f in data['floors'] if f['id']=='attic')
bedroom=next(r for r in attic['rooms'] if r['id']=='attic-bedroom')
bedroom['polygon']=[[-3.4,-2.75],[1.0,-2.75],[1.0,.55],[-3.4,.55]]
storage=next(r for r in attic['rooms'] if r['id']=='attic-closet')
storage['polygon']=[[-3.4,.75],[-1.5,.75],[-1.5,2.75],[-3.4,2.75]]
zolder=next(r for r in attic['rooms'] if r['id']=='attic-hall')
zolder['polygon']=[[-1.3,.75],[1.0,.75],[1.0,2.75],[-1.3,2.75]]
stairs_room=next(r for r in attic['rooms'] if r['id']=='attic-stairs')
stairs_room['polygon']=[[-1.3,1.70],[1.0,1.70],[1.0,2.75],[-1.3,2.75]]
laundry=next(r for r in attic['rooms'] if r['id']=='laundry')
laundry['polygon']=[[1.2,-2.75],[3.4,-2.75],[3.4,2.75],[1.2,2.75]]

# Wall centre lines. The three structural room partitions are 20 cm thick in
# the renderer below; their centre lines therefore sit exactly in the 20 cm
# gaps between the clear room polygons. Knee walls are 10 cm thick and are
# centred outside the usable room, leaving their inner faces at x=-3.40/3.40.
attic['walls']=[
 [-4,-2.75,4,-2.75],
 [-4,-2.75,-4,2.75],
 [-4,2.75,4,2.75],
 [4,-2.75,4,2.75],
 [-3.45,-2.75,-3.45,2.75],
 [3.45,-2.75,3.45,2.75],
 [1.1,-2.75,1.1,.90],
 [1.1,1.80,1.1,2.75],
 [-3.4,.65,-2.85,.65],
 [-2.05,.65,-1.5,.65],
 [-1.3,.65,-.4,.65],
 [.4,.65,1.0,.65],
 [-1.4,.75,-1.4,2.75],
]

bpy.ops.wm.read_factory_settings(use_empty=True)
bpy.context.scene.unit_settings.system='METRIC'
def mat(name,color):
 m=bpy.data.materials.new(name);m.diffuse_color=(*color,1);m.use_nodes=True;m.node_tree.nodes['Principled BSDF'].inputs['Base Color'].default_value=(*color,1);return m
wallmat=mat('Warm lime plaster',(0.77,0.75,0.69));wood=mat('Natural oak',(0.47,0.34,0.21));tile=mat('Warm stone',(0.58,0.59,0.56));edge=mat('Cut wall cap',(0.27,0.29,0.30))
brick=mat('Outer brick',(0.37,0.20,0.13));cavity=mat('Insulated cavity section',(0.33,0.34,0.30))
furniture=json.loads(Path(os.environ.get('FURNITURE_INPUT',str(ROOT/'assets/blender/furniture.json'))).read_text())['items']
build_furniture=runpy.run_path(str(ROOT/'assets/blender/build_furniture.py'))['build_furniture']
custom_module=runpy.run_path(str(ROOT/'assets/blender/build_custom_furniture.py'))
build_custom_furniture=custom_module['build_custom_furniture'];custom_kinds=custom_module['CUSTOM_KINDS']
palette={'oak':mat('Furniture oak',(.52,.36,.22)),'fabric':mat('Warm grey upholstery',(.22,.25,.25)),'fabric_light':mat('Cushion fabric',(.38,.41,.39)),'linen':mat('Cotton linen',(.86,.84,.76)),'blue':mat('Muted blue bedding',(.21,.35,.43)),'dark':mat('Graphite',(.035,.04,.045)),'screen':mat('TV glass',(.018,.035,.05)),'ceramic':mat('Porcelain',(.88,.88,.83)),'basin':mat('Recessed basin',(.41,.47,.47)),'green':mat('Foliage',(.12,.28,.08)),'basket':mat('Woven baskets',(.48,.37,.24)),'mirror':mat('Mirror glass',(.58,.69,.74)),'stone':tile,'metal':mat('Brushed metal',(.35,.38,.4))}
for f in data['floors']:
 bpy.ops.object.select_all(action='DESELECT');objects=[]
 for room in f['rooms']:
  # On the attic the stair footprint is metadata for the map. The actual floor
  # is the Zolder (attic-hall) polygon with the stair opening cut out below.
  if f['id']=='attic' and room['id']=='attic-stairs':
   continue
  verts=[(x,y,0) for x,y in room['polygon']];mesh=bpy.data.meshes.new(room['id']);mesh.from_pydata(verts,[],[list(range(len(verts)))]);mesh.update()
  o=bpy.data.objects.new('room__'+room['id'],mesh);bpy.context.collection.objects.link(o);o.data.materials.append(tile if any(s in room['id'] for s in ['wc','bath','utility','laundry','hall','stairs']) else wood)
  solid=o.modifiers.new('Floor thickness','SOLIDIFY');solid.thickness=.15;objects.append(o)
  cut_stairs=(f['id']=='upper' and room['id']=='upper-stairs') or (f['id']=='attic' and room['id']=='attic-hall')
  if cut_stairs:
   stair=next(i for i in furniture if i['kind']=='winder_stairs' and i['floor']==f['id'])
   bpy.ops.mesh.primitive_cube_add(size=1,location=(stair['x'],stair['y'],0))
   bpy.context.object.dimensions=(stair['width']+.03,stair['depth']+.03,1)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
   cutter=bpy.context.object;opening=o.modifiers.new('Stair opening','BOOLEAN');opening.operation='DIFFERENCE';opening.object=cutter
   bpy.context.view_layer.objects.active=o
   bpy.ops.object.modifier_apply(modifier=solid.name);bpy.ops.object.modifier_apply(modifier=opening.name)
   bpy.data.objects.remove(cutter,do_unlink=True)
 for i,(x,y,X,Y) in enumerate(f['walls']):
  length=math.hypot(X-x,Y-y)
  # The perimeter coordinates are the supplied INTERNAL wall faces.
  normals=([(0,-1),(-1,0),(-1,0),(-1,0),(0,1),(-1,0),(0,1),(1,0),(1,0),(1,0)] if f['id']=='ground' else [(0,-1),(-1,0),(0,1),(1,0)])
  outside=normals[i] if i<len(normals) else None
  inner_thickness=.10
  if f['id']=='attic' and outside is None:
   vertical=abs(x-X)<1e-4
   horizontal=abs(y-Y)<1e-4
   if (vertical and (abs(x-1.1)<1e-4 or abs(x+1.4)<1e-4)) or (horizontal and abs(y-.65)<1e-4):
    inner_thickness=.20
  layers=[(.10,.05,wallmat),(.12,.16,cavity),(.10,.27,brick)] if outside else [(inner_thickness,0,wallmat)]
  for layer,(thickness,offset,material) in enumerate(layers):
   dx,dy=outside or (0,0)
   bpy.ops.mesh.primitive_cube_add(size=1,location=((x+X)/2+dx*offset,(y+Y)/2+dy*offset,.65))
   o=bpy.context.object;o.name=f"wall__{f['id']}__{i}__{layer}";o.dimensions=(length,thickness,1.3);o.rotation_euler.z=math.atan2(Y-y,X-x)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True);o.data.materials.append(material);o['provisional_full_height_m']=2.6;objects.append(o)
 regular=[item for item in furniture if item.get('model_kind') not in custom_kinds]
 objects.extend(build_furniture(regular,f,palette))
 objects.extend(build_custom_furniture(furniture,f,palette))
 bpy.ops.object.select_all(action='DESELECT')
 for o in objects:o.select_set(True)
 bpy.ops.export_scene.gltf(filepath=str(OUTPUT/f"{f['id']}.glb"),export_format='GLB',use_selection=True,export_extras=True)
 for o in objects:o.hide_set(True)
for o in bpy.data.objects:
 if o.name.startswith('furniture__ground') or o.name.startswith('wall__ground') or o.name.removeprefix('room__') in [r['id'] for r in data['floors'][0]['rooms']]:o.hide_set(False)
versions={f['id']:hashlib.sha256((OUTPUT/f"{f['id']}.glb").read_bytes()).hexdigest()[:12] for f in data['floors']}
if 'FURNITURE_OUTPUT' not in os.environ:
 (ROOT/'resources/js/model-versions.json').write_text(json.dumps(versions,indent=2)+'\n')
if 'FURNITURE_OUTPUT' not in os.environ:bpy.ops.wm.save_as_mainfile(filepath=str(ROOT/'assets/blender/house.blend'))
