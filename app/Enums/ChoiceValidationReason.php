<?php

namespace App\Enums;

enum ChoiceValidationReason: string
{
    case ChoiceExceedsMaximumAllowedLimit = 'CHOICE_EXCEEDS_MAXIMUM_ALLOWED_LIMIT';
    case NoChoiceProvided = 'NO_CHOICE_PROVIDED';
    case ChoiceSequenceGap = 'CHOICE_SEQUENCE_GAP';
    case RegistrationNotFound = 'REGISTRATION_NOT_FOUND';
    case RegistrationIdentityMismatch = 'REGISTRATION_IDENTITY_MISMATCH';
    case DuplicateChoice = 'DUPLICATE_CHOICE';
    case UnknownCode = 'UNKNOWN_CODE';
    case InactiveMasterCode = 'INACTIVE_MASTER_CODE';
    case NotInFinalizedCircular = 'NOT_IN_FINALIZED_CIRCULAR';
    case TrackNotAllowed = 'TRACK_NOT_ALLOWED';
    case BachelorSubjectMismatch = 'BACHELOR_SUBJECT_MISMATCH';
    case PrsMismatch = 'PRS_MISMATCH';
    case BachelorAndPrsMismatch = 'BACHELOR_AND_PRS_MISMATCH';
    case ParentNoEligibleSubCadre = 'PARENT_NO_ELIGIBLE_SUB_CADRE';
    case CandidateNotChoiceEligible = 'CANDIDATE_NOT_CHOICE_ELIGIBLE';
    case CandidateFailedInViva = 'CANDIDATE_FAILED_IN_VIVA';
    case CandidateInactiveInViva = 'CANDIDATE_INACTIVE_IN_VIVA';
    case CandidateMissingVivaResult = 'CANDIDATE_MISSING_VIVA_RESULT';
    case CandidateUnresolvedWrittenTrack = 'CANDIDATE_UNRESOLVED_WRITTEN_TRACK';
    case NoValidChoiceRemains = 'NO_VALID_CHOICE_REMAINS';
}
