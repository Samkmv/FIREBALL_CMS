/* Binary helpers used by the bundled WebTorrent on browsers before Baseline 2025. */
(() => {
    'use strict';
    const install=(target,name,value)=>{if(typeof target[name]!=='function')Object.defineProperty(target,name,{value,writable:true,configurable:true});};
    install(Uint8Array.prototype,'toBase64',function(options={}){
        if(!(this instanceof Uint8Array))throw new TypeError('Expected Uint8Array');
        if(!options || typeof options!=='object')throw new TypeError('Expected options object');
        const alphabet=options.alphabet??'base64';if(!['base64','base64url'].includes(alphabet))throw new TypeError('Invalid base64 alphabet');
        const chunks=[];for(let index=0;index<this.length;index+=8192)chunks.push(String.fromCharCode(...this.subarray(index,index+8192)));
        let result=btoa(chunks.join(''));if(alphabet==='base64url')result=result.replace(/\+/g,'-').replace(/\//g,'_');
        return options.omitPadding?result.replace(/=+$/,''):result;
    });
    install(Uint8Array.prototype,'toHex',function(){
        if(!(this instanceof Uint8Array))throw new TypeError('Expected Uint8Array');
        let result='';for(const byte of this)result+=byte.toString(16).padStart(2,'0');return result;
    });
    install(Uint8Array,'fromHex',function(value){
        if(typeof value!=='string')throw new TypeError('Expected hex string');
        if(value.length%2 || /[^0-9a-f]/i.test(value))throw new SyntaxError('Invalid hex string');
        const result=new Uint8Array(value.length/2);for(let index=0;index<result.length;index++)result[index]=parseInt(value.slice(index*2,index*2+2),16);return result;
    });
})();
