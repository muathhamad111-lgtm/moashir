<?php

declare(strict_types=1);

return [

    'validation_failed' => 'The submitted data is invalid.',
    'unauthenticated' => 'Please sign in to continue.',
    'forbidden' => 'You do not have permission to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'method_not_allowed' => 'This method is not allowed for the requested resource.',
    'rate_limited' => 'Too many requests. Please try again later.',
    'server_error' => 'An unexpected error occurred. Please try again later.',
    'service_unavailable' => 'The service is temporarily unavailable.',
    'http_error' => 'The request could not be completed.',
    'reason_required' => 'A reason is required for this action.',
    'role_not_assignable' => 'This role cannot be assigned through the platform.',
    'sod_role_combination' => 'These roles cannot be held together by one user.',
    'sod_self_grant' => 'You cannot change your own roles or permissions.',
    'sod_conflict' => 'This action conflicts with the segregation-of-duties rules.',
    'account_locked' => 'The account is temporarily locked. Please try again later.',
    'account_deactivated' => 'This account is not active.',
    'request' => [
        'malformed' => 'The request is malformed.',
        'not_acceptable' => 'The API only returns JSON. Send the header Accept: application/json.',
        'too_large' => 'The request is too large.',
    ],
    'csrf' => [
        'token_mismatch' => 'Your session has expired. Please refresh and try again.',
    ],
    'session' => [
        'expired' => 'Your session has expired. Please sign in again.',
    ],
    'state' => [
        'transition_illegal' => 'This action is not allowed in the current state.',
    ],
    'credentials' => [
        'invalid' => 'The email or password is incorrect.',
    ],
    'login' => [
        'mode_disabled' => 'This sign-in method is not enabled.',
    ],
    'otp' => [
        'invalid' => 'The verification code is incorrect.',
        'expired' => 'The verification code has expired. Please request a new one.',
        'challenge_invalid' => 'This verification request is no longer valid. Please request a new code.',
        'attempts_exceeded' => 'Too many incorrect attempts. Please request a new code.',
        'cooldown' => 'Please wait before requesting a new code.',
    ],
    'mfa' => [
        'attempts_exceeded' => 'Too many incorrect codes. Please sign in again.',
        'already_enrolled' => 'Two-factor authentication is already set up for this account.',
        'invalid' => 'The authentication code is incorrect.',
        'required' => 'Two-factor authentication must be set up before continuing.',
        'required_for_role' => 'Two-factor authentication is mandatory for your account and cannot be disabled.',
        'not_pending' => 'There is no sign-in waiting for verification. Please sign in again.',
        'not_enrolled' => 'Two-factor authentication is not set up for this account.',
    ],
    'password' => [
        'change_required' => 'You must set a new password before continuing.',
    ],
    'token' => [
        'invalid' => 'This link is invalid or has expired.',
    ],
    'invitation' => [
        'expired' => 'This invitation has expired. Please ask the System Administrator for a new one.',
        'invalid' => 'This invitation is invalid.',
    ],
    'user' => [
        'self' => 'You cannot perform this action on your own account.',
        'entity_status_managed' => 'Entity accounts follow the status of their entity.',
    ],
    'permission' => [
        'unknown' => 'One or more permissions are not recognised.',
        'locked' => 'This permission is locked to a specific role and cannot be changed.',
    ],
    'role' => [
        'not_editable' => 'The permissions of this role cannot be edited.',
    ],
    'confirmation' => [
        'mismatch' => 'The typed confirmation does not match the entity reference.',
    ],
    'disclaimer' => [
        'required' => 'You must acknowledge that permanent deletion cannot be undone.',
    ],
    'entity' => [
        'deleted' => 'This entity was deleted. Its records are frozen until the entity is restored.',
        'open_requests_confirmation_required' => 'This entity has open requests. Review the list and confirm the deletion to continue.',
        'not_soft_deleted' => 'Only an entity in the Deleted entities section can be deleted permanently.',
        'verification_cooldown' => 'A verification reminder was sent recently. Please try again later.',
    ],
    'email_change' => [
        'pending_approval' => 'An official e-mail change is already awaiting approval.',
        'address_taken' => 'This e-mail address can no longer be used. Please choose another one.',
    ],
    'registration' => [
        'terms_unavailable' => 'Registration is not available at the moment. Please try again later.',
    ],
    'export' => [
        'too_large' => 'The export is too large. Narrow the filters and try again.',
    ],
    'order' => [
        'incomplete' => 'The new order must list every item of the list exactly once.',
    ],
    'criteria' => [
        'no_draft' => 'There is no draft criteria version. Create a draft from the published version first.',
        'draft_exists' => 'A draft criteria version already exists. Publish or discard it before creating another one.',
        'version_not_draft' => 'Only a draft criteria version can be changed this way. Create a new draft to change published criteria.',
        'version_is_draft' => 'A draft version is edited directly. Text corrections apply to published or archived versions only.',
        'no_published_version' => 'No published criteria version is available.',
        'clone_source_invalid' => 'Choose a published or archived criteria version to copy.',
        'weights_inconsistent' => 'The criteria cannot be published until the weights are consistent.',
        'scope_required' => 'Choose where this change applies: all assessments, new requests only, or reset the open requests.',
        'weight_exceeds_remaining' => 'The weight exceeds the remaining weight of the parent criterion.',
        'weight_exceeds_remaining_detail' => 'The weight exceeds the remaining weight of :remaining.',
        'weight_below_allocated' => 'The weight is lower than the weight already allocated to its items.',
        'weight_below_allocated_detail' => 'The weight cannot be lower than the :allocated already allocated to its items.',
        'weight_required' => 'Enter a weight, or turn on equal distribution in the parent criterion.',
        'weight_too_small_to_distribute' => 'The weight is too small to be distributed equally.',
        'weight_too_small_to_distribute_detail' => 'The weight :weight cannot be distributed equally over :shares items.',
        'sub_evaluated_directly' => 'This sub-criterion is evaluated directly, so guiding points cannot be scored on it.',
        'points_confirm_required' => 'Switching to direct evaluation deletes the guiding points of this sub-criterion.',
        'points_confirm_required_detail' => 'Switching to direct evaluation deletes :count guiding points. Confirm to continue.',
        'node_not_in_version' => 'This item does not belong to the selected criteria version.',
        'problems' => [
            'no_active_main' => 'The version needs at least one active main criterion.',
            'weights_mismatch' => 'The active sub-criteria of :code total :actual, but they must equal the main criterion weight of :expected.',
            'no_active_point' => 'The sub-criterion :code is evaluated on guiding points but has no active guiding point.',
            'points_exceed_sub' => 'The active guiding points of :code total :actual, which exceeds the sub-criterion weight of :expected.',
        ],
    ],
    'self' => [
        'draft_exists' => 'You already have an open self-assessment draft. Continue it or submit it before starting a new one.',
    ],
    'scores' => [
        'incomplete' => 'Score every item before submitting the assessment.',
        'item_missing' => 'The item :code has no score yet.',
    ],
    'score' => [
        'use_points' => 'This sub-criterion is scored through its guiding points.',
        'item_not_in_version' => 'This item is not part of the criteria version of this assessment.',
        'out_of_range' => 'The score must be a number between zero and the maximum of the assessment scale, with at most two decimal places.',
    ],
    'assessment' => [
        'sub_not_in_version' => 'This sub-criterion is not part of the criteria version of this assessment.',
        'not_assignee' => 'Only the assessor assigned to this request can do this.',
        'assignment_required' => 'This request has no assigned assessor.',
        'result_required' => 'This request has no computed result yet.',
        'entity_required' => 'Only an entity account can start a self assessment.',
        'entity_not_active' => 'The entity account must be active to take part in assessments.',
        'criteria_update_in_progress' => 'The assessment criteria of this draft are being updated after the entity account was restored. Try submitting again shortly.',
    ],
    'provider_error' => 'The provider rejected the request or could not be reached. Review the settings and try again.',
    'provider_unavailable' => 'This service is temporarily unavailable. Please try again later.',
    'integration' => [
        'not_configured' => 'This integration is not configured yet. Save its credentials first.',
        'test_required' => 'Run a successful connection test of this environment before activating it.',
        'configuration_incomplete' => 'Complete the required fields of this provider before activating it.',
    ],
    'ai' => [
        'data_transfer_acknowledgement_required' => 'Acknowledge that visitor questions and knowledge-base excerpts are sent to a provider outside the Kingdom before activating it.',
    ],
    'payment' => [
        'price_not_configured' => 'The price of this service is not set yet. Please try again later.',
        'entity_required' => 'Only an entity account can pay for a service.',
        'entity_not_active' => 'The entity account must be active to pay for a service.',
        'checkout_in_progress' => 'This payment is being prepared. Please try again in a moment.',
        'binding_mismatch' => 'This payment reference does not belong to this payment order.',
        'provider_payment_not_found' => 'The payment could not be found at the payment gateway.',
        'verification_rejected' => 'The payment returned by the gateway does not match this payment order.',
        'provider_payment_id_required' => 'Enter the gateway payment reference to mark the payment as paid.',
        'provider_payment_id_in_use' => 'This gateway payment reference is already linked to another payment.',
        'method_requires_confirmation' => 'Apple Pay and STC Pay can be enabled only after their activation steps are confirmed.',
        'confirmation_in_use' => 'This confirmation cannot be withdrawn while the payment method that depends on it is enabled.',
        'webhook_requires_https' => 'Automatic webhook registration needs the platform to run on an HTTPS address.',
        'apple_pay_file_missing' => 'No Apple Pay domain association file has been uploaded.',
    ],
    'sms' => [
        'recipient_not_in_test_numbers' => 'In test mode, messages can be sent only to the allowed test numbers.',
        'template_not_approved' => 'This text does not match any approved Msegat template of the sender.',
        'templates_not_loaded' => 'Load the approved Msegat templates before saving the message templates.',
    ],
    'email' => [
        'host_not_allowed' => 'This mail server address is not allowed. Use the public host name of the mail server.',
        'test_address_required' => 'Enter a test mailbox before switching e-mail to test mode.',
    ],
    'webhook' => [
        'unauthenticated' => 'The notification could not be verified.',
        'malformed' => 'The notification is not valid.',
    ],
    'idempotency_key' => [
        'missing' => 'Send a unique Idempotency-Key header (UUID) with this request.',
        'reused' => 'This Idempotency-Key was already used for a different request.',
    ],
    'purpose' => [
        'not_available' => 'This service cannot be paid through the platform.',
    ],
    'refund' => [
        'amount_must_equal_total' => 'Only full refunds can be recorded. The amount must equal the payment total of :total.',
        'date_before_payment' => 'The refund date cannot be before the payment date.',
        'already_recorded' => 'A refund is already recorded for this payment.',
    ],
    'receipt' => [
        'voided' => 'This payment receipt was voided and cannot be reissued.',
        'not_issued' => 'No payment receipt has been issued for this payment.',
    ],
    'finance_export' => [
        'not_ready' => 'This export is not ready yet.',
    ],
    'file' => [
        'content_mismatch' => 'The file type is not allowed or its content does not match its extension.',
        'infected' => 'The file was rejected by the malware scan.',
        'scan_failed' => 'The file could not be scanned for malware. Please try again later.',
        'not_clean' => 'This file is not available for download.',
    ],
    'signature' => [
        'invalid' => 'This download link is not valid.',
        'expired' => 'This download link has expired. Request a new one.',
    ],
    'document' => [
        'renderer_unavailable' => 'The document cannot be generated at the moment. Please try again later.',
    ],
    'accredited' => [
        'request_open' => 'Your entity already has an open accredited assessment request. Complete it before paying for a new one.',
        'payment_being_processed' => 'Your accredited assessment payment is being processed. Your request will open shortly.',
    ],
    'cv' => [
        'not_allowed' => 'This sub-criterion does not take a CV entry.',
    ],
    'completion' => [
        'item_not_requested' => 'This item is not part of the completion request. Only the requested items can be edited.',
        'deadline_passed' => 'The completion deadline of this request has passed. The request now follows the outcome set when the completion was requested.',
        'deadline_after_committee_start' => 'The completion deadline cannot be later than the committee assessment start of the cycle (:date).',
    ],
    'attachment' => [
        'key_invalid' => 'This attachment type is not defined for the sub-criterion.',
        'sub_full' => 'This sub-criterion already has the maximum of :max evidence files.',
    ],
    'submission' => [
        'incomplete' => 'Complete the required CV entries and attachments before submitting the request.',
        'cv_missing' => 'The CV entry of :code is required.',
        'attachment_missing' => 'A required attachment of :code is missing.',
    ],
    'assessor' => [
        'invalid' => 'Choose an active assessor account.',
        'same' => 'The request is already assigned to this assessor.',
        'not_in_cycle' => 'This assessor is not an initial assessor of the award cycle of this request.',
    ],
    'sod_self_assignment' => 'You cannot assign a request to yourself.',
    'evidence' => [
        'bundle_empty' => 'This request has no evidence file available for download.',
        'bundle_not_ready' => 'This evidence bundle is not ready or has expired. Request a new one.',
    ],
    'cycle' => [
        'committee_not_started' => 'The committee assessment of this cycle has not started.',
        'another_open' => 'Another award cycle is already running. Close it before opening a new one.',
        'dates_unordered' => 'The cycle dates must follow each other: submission start, submission end, committee assessment start, committee assessment end, then the announcement date. While submissions are open, the submission end must be in the future.',
        'field_locked' => 'This field can no longer be changed at the current stage of the cycle.',
        'region_has_requests' => 'A region that already has requests in this cycle cannot be removed.',
        'criteria_version_not_published' => 'Choose the published criteria version.',
        'participation_setting_reserved' => 'Only the System Administrator turns the participation certificate of a cycle on or off.',
        'submission_window_passed' => 'The submission period of this cycle has already ended.',
        'requests_pending' => 'The cycle cannot be closed while some of its requests are still in progress.',
        'submission_closed' => 'The submission period of the award cycle is not open.',
        'conditions_failed' => 'Your entity does not meet the entry conditions of the award cycle.',
        'member_invalid' => 'Choose active staff accounts that hold the required role.',
        'initial_assessor_invalid' => 'Choose active assessor accounts.',
        'filtering_not_open' => 'Filtering decisions can be taken only after the submission period of the cycle has closed.',
    ],
    'advanced' => [
        'request_exists' => 'Your entity already has a request in this award cycle.',
        'conditions' => [
            'min_entity_age' => 'The entity must be at least :required years old to apply (current age: :actual years).',
            'region' => 'The region of the entity is not covered by this award cycle.',
        ],
    ],
    'filtering' => [
        'override_reason_required' => 'The initial assessment result is below the disqualification score. Give the reason of the exception to qualify this request.',
    ],
    'committee' => [
        'candidate_invalid' => 'Choose an active assessor account.',
        'member_exists' => 'This assessor already sits on the committee of this cycle.',
        'none_active' => 'At least one active committee assessor must remain in the cycle.',
        'evaluation_locked' => 'This evaluation was submitted and is locked. It reopens only when the Award Secretary returns the request to the committee.',
        'membership_inactive' => 'Your committee appointment in this cycle is not active.',
        'incomplete' => 'Not every committee assessor has submitted the evaluation of this request yet.',
        'substitution_pending' => 'A substitute committee assessor result awaits approval for this request. Approve it before approving the average.',
        'substitute_not_completed' => 'The substitute committee assessor has not submitted the evaluation yet.',
    ],
    'committee_assessor' => [
        'has_scores' => 'This committee assessor already started or submitted evaluations. Disable the assessor instead of removing them.',
    ],
    'discussion' => [
        'closed' => 'This committee discussion is closed.',
        'deadline_passed' => 'The deadline of this committee discussion has passed.',
        'entry_kind_invalid' => 'This response does not match the mode of the discussion.',
    ],
    'rank' => [
        'taken' => 'Another request already holds this rank. Clear it first.',
    ],
    'award' => [
        'region_locked' => 'The results of this region are approved and locked.',
        'region_approved' => 'The results of this region are already approved.',
        'region_not_approved' => 'The results of this region are not approved.',
        'national_approved' => 'The national result of this cycle is approved and locked.',
    ],
    'region' => [
        'requests_pending' => 'Some requests of this region have not reached an approved result yet.',
    ],
    'regions' => [
        'pending' => 'Some regions still have requests without an approved result or are not approved yet.',
    ],
    'winners' => [
        'none' => 'Rank at least one winner before approving.',
    ],
];
