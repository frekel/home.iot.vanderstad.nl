"""Extract furniture from the user's draw.io plan; drawing units are scaled to internal walls.
Usage: python3 assets/blender/import_furniture.py /path/to/zuidgors20.xml
The original document is read as data; it is not committed.
"""
import json, math, sys, runpy, xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
cells={c.get('id'):c for c in ET.parse(sys.argv[1]).findall('.//mxCell')}
def geometry(c):
 g=c.find('mxGeometry')
 return {k:float(g.get(k,0)) for k in ['x','y','width','height']} if g is not None else dict.fromkeys(['x','y','width','height'],0)
def absolute(c):
 g=geometry(c);x,y=g['x']+g['width']/2,g['y']+g['height']/2
 style=dict(v.split('=',1) for v in c.get('style','').split(';') if '=' in v)
 rotation=float(style.get('rotation',0));parent=cells.get(c.get('parent'))
 while parent is not None:
  pg=geometry(parent)
  ps=dict(v.split('=',1) for v in parent.get('style','').split(';') if '=' in v)
  degrees=float(ps.get('rotation',0));a=math.radians(degrees)
  dx,dy=x-pg['width']/2,y-pg['height']/2
  x=pg['x']+pg['width']/2+dx*math.cos(a)-dy*math.sin(a)
  y=pg['y']+pg['height']/2+dx*math.sin(a)+dy*math.cos(a)
  rotation+=degrees;parent=cells.get(parent.get('parent'))
 return x,y,rotation
kinds={'couch':'sofa','sofa':'armchair','dresser':'cabinet','elevator':'cabinet','bed_double':'bed','flat_tv':'tv','office_chair':'chair','chair':'chair','refrigerator':'fridge','range_1':'cooker','sink_22':'sink','sink_double2':'sink','toilet':'toilet','bathtub2':'bath','plant':'plant','water_cooler':'cabinet','workstation':'computer','rect':'table'}
items=[]
for id,c in cells.items():
 style=dict(v.split('=',1) for v in c.get('style','').split(';') if '=' in v)
 shape=style.get('shape','').split('.')[-1]
 if shape not in kinds:continue
 cx,cy,rotation=absolute(c);g=geometry(c)
 if cy<430:floor='ground';ox,oy,sx,sy,L=90,131,11/530,5.5/260,11
 elif cy<780:floor='upper';ox,oy,sx,sy,L=91,470,8/370,5.5/260,8
 else:floor='attic';ox,oy,sx,sy,L=90,810,8/370,5.5/260,8
 kind=kinds[shape];height={'sofa':.85,'armchair':.85,'cabinet':.85,'bed':.58,'tv':.65,'chair':.85,'fridge':1.8,'cooker':.9,'sink':.9,'toilet':.65,'bath':.6,'plant':1.1,'computer':.4,'table':.75}[kind]
 # The drawing uses cabinet symbols for kitchen units and washing appliances.
 if floor=='attic' and id.rsplit('-',1)[-1] in ['272','273']:kind='washer';height=.85
 items.append(dict(id=id.rsplit('-',1)[-1],floor=floor,kind=kind,x=round(L/2-(cx-ox)*sx,4),y=round(2.75-(cy-oy)*sy,4),width=round(g['width']*sx,4),depth=round(g['height']*sy,4),height=height,rotation=180+rotation,source_shape=shape))
# Bed 239 is in Levi's room; replace its former wall TV (238).
items=[i for i in items if not (i['floor']=='upper' and i['id']=='238')]
bed=next(i for i in items if i['floor']=='upper' and i['id']=='239')
angle=math.radians(bed['rotation']);distance=bed['depth']/2+.4/2+.03
cabinet=dict(id='levi-tv-cabinet',floor='upper',kind='cabinet',x=round(bed['x']-math.sin(angle)*distance,4),y=round(bed['y']+math.cos(angle)*distance,4),width=1.1,depth=.4,height=.8,rotation=bed['rotation'],measured=True)
items.append(cabinet)
items.append(dict(id='levi-tv',floor='upper',kind='tv',x=cabinet['x'],y=cabinet['y'],width=1.0,depth=.06,height=.56,base_z=.9,rotation=cabinet['rotation']+180,standing=True))
# User correction: Z-272/Z-273 quarter-turn counterclockwise on the mirrored attic map.
for item in items:
 if item['floor']=='attic' and item['id'] in ['272','273']:
  item['rotation']=90
items=runpy.run_path(str(ROOT/'assets/blender/furniture_adjustments.py'))['apply_adjustments'](items)
(ROOT/'assets/blender/furniture.json').write_text(json.dumps({'source':'zuidgors20.xml','note':'Positions and footprints from draw.io. Heights/materials approximate except measured Levi cabinet.','items':items},indent=2)+'\n')
print('Furniture:',len(items),'items')
