<?php

const REPORT_CATEGORY_OTHER = 0;

const REPORT_CATEGORY_MOD_MALICIOUS      = 1;
const REPORT_CATEGORY_MOD_DISCRIMINATION = 2;
const REPORT_CATEGORY_MOD_COPYRIGHT      = 3;
const REPORT_CATEGORY_MOD_LICENSING      = 4;
const REPORT_CATEGORY_MOD_ENGLISH        = 5;
const REPORT_CATEGORY_MOD_EXPLANATION    = 6;
const REPORT_CATEGORY_MOD_SPAM           = 7;
const REPORT_CATEGORY_MOD_PIRACY         = 8;
const REPORT_CATEGORY_MOD_LOW_EFFORT_AI  = 9;
// :MaxReportCategoryMod

const REPORT_CATEGORIES_MOD = [
	REPORT_CATEGORY_MOD_MALICIOUS      => "Harmful or malicious content.",
	REPORT_CATEGORY_MOD_DISCRIMINATION => "Defamation, bullying, obscenity, discrimination, provocations.",
	REPORT_CATEGORY_MOD_COPYRIGHT      => "Breach of copyright.",
	REPORT_CATEGORY_MOD_LICENSING      => "Incompatible licensing. Mod tries to restrict or violate Anego Studios's license.",
	REPORT_CATEGORY_MOD_ENGLISH        => "Missing english description.",
	REPORT_CATEGORY_MOD_EXPLANATION    => "Missing clear explanation of function.",
	REPORT_CATEGORY_MOD_SPAM           => "Spamming or other bot-like behavior.",
	REPORT_CATEGORY_MOD_PIRACY         => "Advocation of piracy.",
	REPORT_CATEGORY_MOD_LOW_EFFORT_AI  => "Very low effort / AI-generated content.",

	REPORT_CATEGORY_OTHER => "Other.",
];


const REPORT_CATEGORY_COMMENT_DISCRIMINATION = 1;
const REPORT_CATEGORY_COMMENT_SPAM           = 2;
const REPORT_CATEGORY_COMMENT_PIRACY         = 3;
// :MaxReportCategoryComment

const REPORT_CATEGORIES_COMMENT = [
	REPORT_CATEGORY_COMMENT_DISCRIMINATION => "Defamation, bullying, obscenity, discrimination, provocations.",
	REPORT_CATEGORY_COMMENT_SPAM           => "Spamming or other bot-like behavior.",
	REPORT_CATEGORY_COMMENT_PIRACY         => "Advocation of piracy.",

	REPORT_CATEGORY_OTHER => "Other.",
];


const MOD_REQUEST_KIND_REPORT_MOD     = 1;
const MOD_REQUEST_KIND_REPORT_COMMENT = 2;
const MOD_REQUEST_KIND_MANUAL_REVIEW  = 3;

// 8 bit
//const MOD_REQUEST_FLAG_ASSIGNED = 1 << 0;
const MOD_REQUEST_FLAG_USER_ACTION_REQUIRED = 1 << 4;
const MOD_REQUEST_FLAG_SPAM                 = 1 << 5;
const MOD_REQUEST_FLAG_WONTFIX              = 1 << 6;
const MOD_REQUEST_FLAG_CLOSED               = 1 << 7;


/**
 * @param int $flags
 * @return string
 */
function stringifyModerationRequestStateForUser($flags)
{
	$result = 'Open';
	if($flags & MOD_REQUEST_FLAG_WONTFIX) $result = 'Wont Fix';
	else if($flags & MOD_REQUEST_FLAG_SPAM) $result = 'Closed';
	else if($flags & MOD_REQUEST_FLAG_CLOSED) $result = 'Solved';
	else if($flags & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED) $result = 'User Action Required';
	return $result;
}

/**
 * @param int $flags
 * @return string
 */
function stringifyModerationRequestState($flags)
{
	$result = 'Open';
	if($flags & MOD_REQUEST_FLAG_WONTFIX) $result = 'Wont Fix';
	else if($flags & MOD_REQUEST_FLAG_SPAM) $result = 'Spam';
	else if($flags & MOD_REQUEST_FLAG_CLOSED) $result = 'Solved';
	else if($flags & MOD_REQUEST_FLAG_USER_ACTION_REQUIRED) $result = 'User Action Required';
	return $result;
}

/**
 * @param array{kind: int, category: int} $request
 * @return string
 */
function stringifyModerationRequestKind($request)
{
	switch($request['kind']) {
		case MOD_REQUEST_KIND_REPORT_MOD:
			switch($request['category']) {
				case REPORT_CATEGORY_MOD_MALICIOUS:      return 'Mod Report - Malicious';
				case REPORT_CATEGORY_MOD_DISCRIMINATION: return 'Mod Report - Defamation / Bullying / Obscenity / Discrimination / Provocations';
				case REPORT_CATEGORY_MOD_COPYRIGHT:      return 'Mod Report - Copyright';
				case REPORT_CATEGORY_MOD_LICENSING:      return 'Mod Report - Licensing';
				case REPORT_CATEGORY_MOD_ENGLISH:        return 'Mod Report - English';
				case REPORT_CATEGORY_MOD_EXPLANATION:    return 'Mod Report - Explanation';
				case REPORT_CATEGORY_MOD_SPAM:           return 'Mod Report - Spam';
				case REPORT_CATEGORY_MOD_PIRACY:         return 'Mod Report - Piracy';
				case REPORT_CATEGORY_MOD_LOW_EFFORT_AI:  return 'Mod Report - (AI) Low Effort';
				case REPORT_CATEGORY_OTHER:              return 'Mod Report';
			}
			break;

		case MOD_REQUEST_KIND_REPORT_COMMENT:
			switch($request['category']) {
				case REPORT_CATEGORY_COMMENT_DISCRIMINATION: return 'Comment Report - Defamation / Bullying / Obscenity / Discrimination / Provocations';
				case REPORT_CATEGORY_COMMENT_SPAM:           return 'Comment Report - Spam';
				case REPORT_CATEGORY_COMMENT_PIRACY:         return 'Comment Report - Piracy';
				case REPORT_CATEGORY_OTHER:                  return 'Comment Report';
			}
			break;

		case MOD_REQUEST_KIND_MANUAL_REVIEW:
			return 'Request for manual review';
	}
	return "{$request['kind']} - {$request['category']}"; // fallback
}

/**
 * @param array{kind: int, category: int} $request
 * @return string
 */
function stringifyModerationRequestCategory($request)
{
	switch($request['kind']) {
		case MOD_REQUEST_KIND_REPORT_MOD:
			switch($request['category']) {
				case REPORT_CATEGORY_MOD_MALICIOUS:      return 'Malicious';
				case REPORT_CATEGORY_MOD_DISCRIMINATION: return 'Defamation / Bullying / Obscenity / Discrimination / Provocations';
				case REPORT_CATEGORY_MOD_COPYRIGHT:      return 'Copyright';
				case REPORT_CATEGORY_MOD_LICENSING:      return 'Licensing';
				case REPORT_CATEGORY_MOD_ENGLISH:        return 'English';
				case REPORT_CATEGORY_MOD_EXPLANATION:    return 'Explanation';
				case REPORT_CATEGORY_MOD_SPAM:           return 'Spam';
				case REPORT_CATEGORY_MOD_PIRACY:         return 'Piracy';
				case REPORT_CATEGORY_MOD_LOW_EFFORT_AI:  return '(AI) Low Effort';
				case REPORT_CATEGORY_OTHER:              return 'Other';
			}
			break;

		case MOD_REQUEST_KIND_REPORT_COMMENT:
			switch($request['category']) {
				case REPORT_CATEGORY_COMMENT_DISCRIMINATION: return 'Defamation / Bullying / Obscenity / Discrimination / Provocations';
				case REPORT_CATEGORY_COMMENT_SPAM:           return 'Spam';
				case REPORT_CATEGORY_COMMENT_PIRACY:         return 'Piracy';
				case REPORT_CATEGORY_OTHER:                  return 'Other';
			}
			break;

		case MOD_REQUEST_KIND_MANUAL_REVIEW:
			return 'Manual Review Request';
	}
	return "{$request['kind']} - {$request['category']}"; // fallback
}


