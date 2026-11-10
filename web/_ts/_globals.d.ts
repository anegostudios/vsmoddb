// Defined in footer.tpl
declare const actiontoken : string;
// Only on some pages
declare const modId : number | undefined;

// Silence qjqeury linter complains
declare const $ : any;
type JQElement = {
	[k : number]: HTMLElement | undefined,
	[k : string]: any,
};

interface jqXHR extends XMLHttpRequest {
	done : (cb : (response : any, textStatus : string, xhr : jqXHR) => void) => jqXHR,
	fail : (cb : (xhr : jqXHR, textStatus : string, error : any) => void) => jqXHR,
	always : (cb : (dataOrXhr : any|jqXHR, textStatus : string, xhrOrError : jqXHR|any) => void) => jqXHR,
	then : (done : (response : any, textStatus : string, xhr : jqXHR) => void, fail : (xhr : jqXHR, textStatus : string, error : any) => void) => jqXHR,
}

// Silence tinymce linter complaints
declare const tinyMCE : any;

interface User {
	hash         : string|null,
	at           : string|null,
	consentFlags : number,
}


interface Window { // extend global window interface
	user : User,
}

interface UploadLimits {
	allowedTypes      : number[],  // FILE_TYPE_*
	allowedExtensions : string[],
	attachmentCount   : number,
	individualSize    : number,
}
