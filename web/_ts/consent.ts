const enum Consent {
	Download = 1 << 0,
	Upload   = 1 << 1,
}
const COOKIE_NAME_CONSENT = 'consent';

function didConsentTo(user : User, consentFlags : number) : boolean
{
	return (user.consentFlags & consentFlags) === consentFlags;
}


function attachConsentPromptDownload() : void
{
	const descriptionWrapper = document.getElementsByClassName('tab-container')[0];
	const consentDialog = R.get<HTMLDialogElement>('download-consent-mdl')!;

	let sourceEl : HTMLAnchorElement|null = null;
	if(window.user.hash) {
		attachDialogSendHandler(consentDialog, () => true, (xhr) => {
			R.attachDefaultFailHandler(xhr, 'Failed to update consent');

			window.open(sourceEl!.href);

			consentDialog.close();
			window.user.consentFlags = Consent.Download;
		})
	}
	else { // Guest
		consentDialog.getElementsByTagName('button')[0].addEventListener('click', e => {
			window.open(sourceEl!.href);

			consentDialog.close();
			window.user.consentFlags = Consent.Download;
			$.cookie(COOKIE_NAME_CONSENT, window.user.consentFlags, { path: '/' }); // guest tracked via session cookie
		})
	}

	for(const directDownloadLink of descriptionWrapper.querySelectorAll('a.mod-dl')) {
		directDownloadLink.addEventListener('click', e => {
			if(didConsentTo(window.user, Consent.Download)) return;

			e.preventDefault();

			sourceEl = e.target as HTMLAnchorElement;
			consentDialog.showModal();
		});
	}
}

