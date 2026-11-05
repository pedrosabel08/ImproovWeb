import assert from 'node:assert/strict';
import {normalize, filterProjects, featureCollection, restoreState} from '../MapaImproov/mapa-data.mjs';
const projects = [
    {obra_id:1,codigo:'AB_C',nome:'Residência Açores',localizacao:'São Paulo, SP',status_id:0,has_glb:true,coordinates:[0,0]},
    {obra_id:2,codigo:'D_E',nome:'Obra B',localizacao:'',status_id:2,has_glb:false,coordinates:null},
];
const filters = {search:'',status:'',model:'',location:''};
assert.equal(normalize('AÇÓRES'), 'acores');
assert.equal(filterProjects(projects,{...filters,search:'residencia acores'})[0].obra_id,1);
assert.equal(filterProjects(projects,{...filters,search:'sao paulo'})[0].obra_id,1);
assert.equal(filterProjects(projects,{...filters,search:'ab_c'})[0].obra_id,1);
assert.deepEqual(filterProjects(projects,{...filters,status:'2',model:'yes'}),[]);
assert.equal(filterProjects(projects,{...filters,status:'2',model:'no'})[0].obra_id,2);
assert.equal(filterProjects(projects,{...filters,location:'São Paulo, SP'}).length,1);
const geo = featureCollection(projects);
assert.equal(geo.features.length,1);
assert.deepEqual(geo.features[0].geometry.coordinates,[0,0]);
assert.equal(restoreState('broken'),null);
assert.equal(restoreState('{}'),null);
const saved = {version:1,projection:'mercator',selected:102,filters,camera:{center:[-53,-14],zoom:2.2,bearing:0,pitch:0}};
const {version,...expected} = saved;
assert.deepEqual(restoreState(JSON.stringify(saved)),expected);
assert.equal(restoreState(JSON.stringify({...saved,camera:{...saved.camera,center:[181,0]}})).camera,null);
assert.equal(restoreState(JSON.stringify({...saved,selected:-1})).selected,null);
console.log('Mapa Improov: 14 assertions passed.');
