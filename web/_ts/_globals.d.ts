// Defined in footer.tpl
declare const assetid : number;
declare const assettypeid : number;
declare const actiontoken : string;
// Only on some pages
declare const modId : number | undefined;

// Silence qjqeury linter complains
declare const $ : any;

interface jqXHR extends XMLHttpRequest {
	done : (cb : (response : any, textStatus : string, xhr : jqXHR) => void) => jqXHR,
	fail : (cb : (xhr : jqXHR, textStatus : string, error : any) => void) => jqXHR,
	always : (cb : (dataOrXhr : any|jqXHR, textStatus : string, xhrOrError : jqXHR|any) => void) => jqXHR,
	then : (done : (response : any, textStatus : string, xhr : jqXHR) => void, fail : (xhr : jqXHR, textStatus : string, error : any) => void) => jqXHR,
}

// Silence tinymce linter complaints
declare const tinyMCE : any;
