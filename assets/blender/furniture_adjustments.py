"""Confirmed changes applied to the original XML import (plan coordinates, metres)."""
import math

def bounds(items):
    points=[]
    for i in items:
        a=math.radians(i['rotation'])
        for sx,sy in [(-1,-1),(-1,1),(1,-1),(1,1)]:
            x,y=sx*i['width']/2,sy*i['depth']/2
            points.append((i['x']+x*math.cos(a)-y*math.sin(a),i['y']+x*math.sin(a)+y*math.cos(a)))
    return min(p[0] for p in points),min(p[1] for p in points),max(p[0] for p in points),max(p[1] for p in points)

def apply_adjustments(items):
    group=[i for i in items if i['floor']=='attic' and i['id'] in ['262','263','264']]
    assert len(group)==3
    left,bottom,right,top=bounds(group)
    cx,cy=(left+right)/2,(bottom+top)/2
    # On the mirrored attic map, counterclockwise means -90 degrees in source XY.
    for i in group:
        dx,dy=i['x']-cx,i['y']-cy
        i['x'],i['y']=cx+dy,cy-dx
        i['rotation']=(i['rotation']-90)%360
        i['group']='Z-G1';i['group_name']='Bureau Lily'
    left,bottom,right,top=bounds(group)
    # Map left = source +X. Keep a 5 cm gap at Lily's wall and stay inside the room.
    tx=1.8062-.05-right
    ty=min(0,.1807-.05-top)
    for i in group:
        i['x']=round(i['x']+tx,4);i['y']=round(i['y']+ty,4)
    bed=next(i for i in items if i['floor']=='attic' and i['id']=='191')
    cabinet=next(i for i in items if i['floor']=='attic' and i['id']=='261')
    tv=next(i for i in items if i['floor']=='attic' and i['id']=='201')
    # The bed's head is toward map left; retain the cabinet and TV at its foot.
    tv_offset=tv['x']-cabinet['x']
    bed['x']=round(1.8062-.05-bed['depth']/2,4)
    cabinet['x']=round(bed['x']-bed['depth']/2-cabinet['depth']/2-.03,4)
    cabinet['y']=bed['y']
    tv['x']=round(cabinet['x']+tv_offset,4);tv['y']=bed['y']
    for i in [bed,cabinet,tv]:
        i['group']='Z-G2';i['group_name']='Bed met tv-kast Lily'
    table=next(i for i in items if i['floor']=='attic' and i['id']=='260')
    table.update(kind='table',width=1.0,height=.5)
    table['y']=min(table['y'],.1807-.05-table['width']/2)
    items.append(dict(id='903',floor='attic',kind='open_wardrobe',x=-1.3459-.3,y=(.2732+2.75)/2,width=2.75-.2732,depth=.6,height=2.2,rotation=90,source_shape='user_specified',note='Opposite Z-271; hanging left and shelves right when facing the mirrored wardrobe. Depth and height provisional.'))
    items.append(dict(id='904',floor='attic',kind='winder_stairs',x=(-1.2425+1.8062)/2,y=(1.5998+2.75)/2,width=2.9,depth=1.05,height=2.6,rotation=0,source_shape='user_specified',note='Rectangular half-turn winder stair from supplied reference; clockwise descending; provisional footprint and floor height.'))
    mirror=next(i for i in items if i['floor']=='attic' and i['id']=='193')
    mirror.update(kind='mirror',width=.4,depth=.035,height=1.6,base_z=0,y=.2248-.05-.035/2,rotation=180)
    mirror['x']=-1.872+mirror['width']/2
    baskets=next(i for i in items if i['floor']=='attic' and i['id']=='271')
    baskets.update(kind='basket_cabinet',width=.8,x=-4+baskets['depth']/2+.005)
    tv.update(kind='projector_screen',width=1.3,depth=.04,height=1.3*9/16,base_z=.9,x=round(tv['x']-.1,4))
    for i in [bed,cabinet,tv]:i['group_name']='Bed met schermkast Lily'
    chair=next(i for i in items if i['floor']=='attic' and i['id']=='259')
    table_lower=bounds([table])[1];chair_upper=bounds([chair])[3]
    items.append(dict(id='905',floor='attic',kind='infrared_panel',x=-4+.04/2,y=round((table_lower+chair_upper)/2,4),width=.6,depth=.04,height=.4,base_z=.2,rotation=270,source_shape='user_specified',note='40 cm high; provisional 60 cm width, 4 cm depth and 20 cm mounting height. Between Z-260 and Z-259.'))
    items.append(dict(id='906',floor='attic',kind='water_heater',x=2.21,y=2.515,width=.45,depth=.45,height=1.1,base_z=.1,rotation=180,source_shape='user_specified',note='Boiler, upper-right of Washok in the mirrored map; provisional dimensions.'))
    items.append(dict(id='907',floor='attic',kind='heating_boiler',x=2.77,y=2.57,width=.45,depth=.35,height=.7,base_z=.6,rotation=180,source_shape='user_specified',note='CV-ketel next to boiler; provisional dimensions.'))
    items.append(dict(id='903',floor='upper',kind='shower',x=1.9128+.85/2+.01,y=2.75-.9/2-.01,width=.85,depth=.9,height=2.0,rotation=180,source_shape='user_specified',note='Shower with semicircular curtain; 85 x 90 cm provisional footprint to fit beside the existing toilet.'))
    # Levi's storage wall: floor cabinets flank the wall cabinets over the bed.
    items=[i for i in items if not (i['floor']=='upper' and i['id'] in ['208','235','levi-tv'])]
    upper={i['id']:i for i in items if i['floor']=='upper'}
    for identifier,x in [('236',.69),('220',3.49)]:
        upper[identifier].update(width=1.0,depth=.6,height=2.2,x=x,y=-2.45,rotation=0,measured=True)
    for identifier,x in [('233',1.64),('234',2.54)]:
        upper[identifier].update(width=.9,depth=.4,height=.4,base_z=1.7,x=x,y=-2.55,rotation=0,
                                 group='V1-G1',group_name='Hangkasten boven bed',measured=True)
    upper['239']['x']=2.09
    cabinet=upper['levi-tv-cabinet'];bed=upper['244']
    cabinet.update(x=round(bed['x']-bed['depth']/2-cabinet['depth']/2,4),y=bed['y'],rotation=270)
    # Keep 253 on its cabinet, facing bed 244; align the screen's right edge.
    screen=upper['253']
    screen.update(x=cabinet['x'],y=cabinet['y']-cabinet['width']/2+screen['width']/2,
                  rotation=270,base_z=cabinet['height']+.1,standing=True)
    items.append(dict(id='levi-tv',floor='upper',kind='tv',x=upper['239']['x'],y=.145,
                      width=1.0,depth=.06,height=.56,base_z=.9,rotation=180,
                      note='Wall-mounted TV facing Levi bed; size and mounting height provisional.'))
    items.append(dict(id='904',floor='upper',kind='wall_shelf',x=upper['239']['x'],y=.0792,
                      width=1.1,depth=.2,height=.035,base_z=.8,rotation=180,
                      source_shape='user_specified',note='Shelf below Levi wall TV; dimensions provisional.'))
    desk=upper['251'];drawers=upper['248']
    # Desk back flush with the room's north wall; seated view is toward +Y.
    desk.update(y=.2292-.05-desk['depth']/2,rotation=180)
    chair=upper['250']
    chair.update(x=desk['x'],y=desk['y']-desk['depth']/2-chair['depth']/2-.10,rotation=0)
    upper['252'].update(x=desk['x'],y=.2292-.05-upper['252']['depth']/2-.025,rotation=180)
    drawers.update(kind='drawer_cabinet',height=.62,depth=.5,rotation=desk['rotation'],
                   x=desk['x']-desk['width']/2+drawers['width']/2+.08,y=desk['y'])
    upper['219'].update(kind='rotating_mirror_cabinet',width=.5,depth=.5,height=1.9,
                        x=4-.25-.01,rotation=180,measured=True)
    stair=next(i for i in items if i['floor']=='attic' and i['id']=='904')
    stair.update(mirror_x=True,note='Half-turn staircase mirrored left-right per user correction.')
    items.append(dict(stair,id='905',floor='upper',x=(-1.2442+1.8081)/2,y=(1.6042+2.75)/2,
                      note='Same mirrored half-turn staircase as Z-904, descending from first floor.'))
    ground={i['id']:i for i in items if i['floor']=='ground'}
    rack=ground['84'];rack.update(kind='shoe_rack',y=-2.75+rack['depth']/2)
    support=ground['53']
    items.append(dict(id='901',floor='ground',kind='breakfast_bar',x=support['x'],
                      y=-2.75+1.5/2,width=1.5,depth=.5,height=1.05,rotation=90,
                      support_height=support['height'],source_shape='user_specified',
                      note='150 x 50 cm bar perpendicular to BG-53; two countertop supports and one outer pole. Height 105 cm provisional.'))
    # Kitchen storage row along the room-facing side of the dividing wall.
    ground['104'].update(kind='wall_cabinet_set',x=2.45,y=.045-.35/2,width=1.8,depth=.35,
                         height=.45,base_z=.4,rotation=180,
                         note='Three 60 cm wide, 45 cm high hanging cabinets; top retained at 85 cm. Depth 35 cm provisional.')
    items.append(dict(id='902',floor='ground',kind='american_fridge',x=3.8,y=.045-.7/2,
                      width=.9,depth=.7,height=1.8,rotation=180,source_shape='user_specified',
                      note='American fridge beside BG-104; 90 x 70 x 180 cm provisional.'))
    for identifier,x in [('56',4.55),('55',5.15)]:
        ground[identifier].update(x=x,y=.045-.6/2,width=.6,depth=.6,height=1.8,rotation=180,
                                  note='60 cm wide and 180 cm high; depth 60 cm provisional.')
    shelving=upper['246']
    shelving.update(kind='open_shelving',width=.3,height=1.8,rotation=180,y=.2292-.05-shelving['depth']/2)
    ground['96'].update(kind='dog_house')
    ground['54'].update(kind='split_door_cabinet',width=1.0,left_door_width=.6,right_door_width=.4,
                        note='Two doors: left 60 cm, right 40 cm when facing the cabinet. Existing height and depth retained.')
    sideboard=ground['86'];sideboard['y']=-2.75+sideboard['depth']/2
    # One continuous L-shaped sofa, touching the right end of the sideboard.
    sofa=ground['73'];left=sideboard['x']+sideboard['width']/2
    sofa.update(kind='corner_sofa',width=3.1232,depth=2.5165,x=left+3.1232/2,
                y=-2.75+2.5165/2,rotation=0,seat_depth=.8462,return_width=1.0577,
                note='Single corner sofa joining BG-86; replaces separate BG-74.')
    items=[i for i in items if not (i['floor']=='ground' and i['id']=='74')]
    table=ground['94'];table['x']=max(table['x'],-5.5+table['width']/2+.10)
    for row,identifiers in enumerate([['88','89','90'],['91','92','93']]):
        for column,identifier in enumerate(identifiers):
            chair=ground[identifier];side=-1 if row==0 else 1
            chair.update(x=table['x']+(column-1)*table['width']/3,
                         y=table['y']+side*(table['depth']/2+chair['depth']/2+.1),
                         rotation=0 if row==0 else 180)
    ground['82']['x']=-5.5+.15+ground['82']['width']/2
    ground['96']['rotation']=(ground['96']['rotation']+45)%360
    # Keep the rotated roof footprint inside the exterior wall.
    ground['96']['y']-=max(0,bounds([ground['96']])[3]+.04-2.3641)
    ground['97']['y']-=.45
    # Align the TV arrangement with the short exterior recess wall.
    shift=-2.5659+ground['101']['width']/2-ground['101']['x']
    for identifier in ['100','101','102','103']:ground[identifier]['x']+=shift
    return items
