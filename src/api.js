/* CLONEFRINGER API client. Configure window.CLONEFRINGER_API_BASE before this file if needed. */
window.CfApi = (() => {
 const base=(window.CLONEFRINGER_API_BASE||'/api').replace(/\/$/,'');
 let csrf='';
 let user=null;
 async function request(path,options={}) {
  const headers={'Accept':'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...(options.headers||{})};
  if(csrf && options.method && options.method!=='GET') headers['X-CSRF-Token']=csrf;
  const response=await fetch(base+path,{credentials:'same-origin',...options,headers});
  const data=await response.json().catch(()=>({ok:false,message:'Respons server tidak valid.'}));
  if(!response.ok || data.ok===false) throw new Error(data.message||('HTTP '+response.status));
  return data;
 }
 async function me(){const data=await request('/auth/me');user=data.user;csrf=data.csrf_token;return user;}
 async function login(username,password){const data=await request('/auth/login',{method:'POST',body:JSON.stringify({username,password})});user=data.user;csrf=data.csrf_token;return user;}
 async function logout(){await request('/auth/logout',{method:'POST',body:'{}'});user=null;csrf='';}
 return {request,me,login,logout,get user(){return user;},get csrf(){return csrf;}};
})();
