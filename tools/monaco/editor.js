import * as monaco from 'monaco-editor/esm/vs/editor/editor.api.js';
import 'monaco-editor/esm/vs/editor/editor.all.js';
import 'monaco-editor/esm/vs/basic-languages/php/php.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/html/html.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/css/css.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/javascript/javascript.contribution.js';
import 'monaco-editor/esm/vs/basic-languages/markdown/markdown.contribution.js';
import 'monaco-editor/esm/vs/language/json/monaco.contribution.js';
import 'monaco-editor/esm/vs/language/css/monaco.contribution.js';
import 'monaco-editor/esm/vs/language/html/monaco.contribution.js';
import 'monaco-editor/esm/vs/language/typescript/monaco.contribution.js';
self.MonacoEnvironment = {
    getWorker: (_moduleId, label) => {
        const name = ({json: 'json', css: 'css', scss: 'css', less: 'css', html: 'html',
            handlebars: 'html', razor: 'html', javascript: 'ts', typescript: 'ts'})[label] || 'editor';
        return new Worker(new URL(`./${name}.worker.js`, import.meta.url), {type: 'module'});
    }
};
export {monaco};
