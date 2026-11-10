<?php

declare(strict_types=1);

return [

    'staff_invited' => [
        'subject' => 'Invitation to join the platform team',
        'line' => 'The System Administrator has created an account for you on the Moashir platform. Use the button below to set your password and set up two-factor authentication.',
        'action' => 'Accept the invitation',
        'expiry' => 'This invitation is valid for :duration and can be used once.',
    ],

    'account_locked' => [
        'subject' => 'Your account has been temporarily locked',
        'line' => 'Your account was locked for :duration after several unsuccessful sign-in attempts.',
        'advice' => 'If these attempts were not made by you, please contact the System Administrator.',
        'admin_title' => 'A user account was locked',
        'admin_body' => 'A user account was locked after several unsuccessful sign-in attempts. You may unlock it from the users screen.',
    ],

    'account_status' => [
        'suspended' => [
            'subject' => 'Your account has been suspended',
            'line' => 'Your account on the Moashir platform has been suspended by the System Administrator.',
        ],
        'active' => [
            'subject' => 'Your account has been reactivated',
            'line' => 'Your account on the Moashir platform is active again.',
        ],
    ],

    'mfa_reset' => [
        'subject' => 'Your two-factor authentication was reset',
        'line' => 'The System Administrator reset your two-factor authentication. You will be asked to set it up again at your next sign-in.',
    ],

    'password_reset' => [
        'subject' => 'Reset your password',
        'line' => 'We received a request to reset the password of your account.',
        'action' => 'Reset the password',
        'expiry' => 'This link is valid for :duration and can be used once.',
        'ignore' => 'If you did not request a password reset, you can ignore this message.',
    ],

    'otp' => [
        'subject' => 'Your verification code',
        'line' => 'Use the following verification code to complete your request on the Moashir platform.',
        'expiry' => 'The code is valid for :duration.',
        'never_share' => 'Never share this code with anyone. The platform team will never ask you for it.',
        'ignore' => 'If you did not request this code, you can ignore this message.',
    ],

    'super_admin_email_changed' => [
        'subject' => 'The sign-in email of a Super Admin account was changed',
        'line' => 'The sign-in email of the Super Admin account was changed to :email.',
        'advice' => 'If you did not make this change, contact the technical owner immediately.',
    ],

    'security_alert' => [
        'footer' => 'This is an automatic security alert from the Moashir platform.',
        'SEC-01' => [
            'title' => 'Super Admin sign-in',
            'body' => 'A Super Admin account has signed in to the platform.',
        ],
        'SEC-02' => [
            'title' => 'Failed Super Admin verification',
            'body' => 'A two-factor authentication attempt for a Super Admin account has failed.',
        ],
        'SEC-03' => [
            'title' => 'Super Admin accounts changed',
            'body' => 'The Super Admin role was granted or revoked from the console.',
        ],
        'SEC-04' => [
            'title' => 'Sensitive action performed',
            'body' => 'An action that requires a recorded reason was performed.',
        ],
        'SEC-06' => [
            'title' => 'Integration secret replaced',
            'body' => 'A secret of an external integration (payment, SMS, e-mail or AI provider) was replaced or removed.',
        ],
        'SEC-07' => [
            'title' => 'Entity sign-in code channels changed',
            'body' => 'The channels allowed for the one-time sign-in codes of entity accounts (e-mail, text message) were changed.',
        ],
        'SEC-05' => [
            'title' => 'Rejected Super Admin assignment',
            'body' => 'An attempt to assign the Super Admin role through the platform was rejected.',
        ],
        'USR-05' => [
            'title' => 'Super Admin email changed',
            'body' => 'The sign-in email of a Super Admin account was changed.',
        ],
    ],
    'entities' => [
        'open_platform' => 'Open the platform',
        'REG-01' => [
            'entity' => [
                'title' => 'Welcome to the platform',
                'body' => 'The registration of :entity was received. Verify your e-mail, then complete the entity profile.',
            ],
            'staff' => [
                'title' => 'New entity registered',
                'body' => 'The entity :entity (:reference) registered on the platform and is pending verification.',
            ],
        ],
        'REG-03' => [
            'entity' => [
                'title' => 'Complete your entity profile',
                'body' => 'Your account is verified. Complete the governance grade, the number of employees and the annual beneficiaries of :entity.',
            ],
        ],
        'REG-04' => [
            'entity' => [
                'title' => 'Entity data updated',
                'body' => 'The core data of :entity was updated by the platform administration.',
            ],
            'staff' => [
                'title' => 'Entity core data edited',
                'body' => 'The core data of :entity (:reference) was edited. Review the change history.',
            ],
        ],
        'REG-05' => [
            'entity' => [
                'title' => 'Your entity account was deactivated',
                'body' => 'The account of :entity on the Moashir platform was deactivated. Contact the platform administration for details.',
            ],
            'staff' => [
                'title' => 'Entity account deactivated',
                'body' => 'The account of :entity (:reference) was deactivated. The reason is recorded in the entity record.',
            ],
        ],
        'REG-06' => [
            'entity' => [
                'title' => 'Your entity account was reactivated',
                'body' => 'The account of :entity on the Moashir platform is active again. You can sign in.',
            ],
            'staff' => [
                'title' => 'Entity account reactivated',
                'body' => 'The account of :entity (:reference) was reactivated.',
            ],
        ],
        'REG-07' => [
            'entity' => [
                'title' => 'Verify the e-mail of your entity account',
                'body' => 'The registration of :entity is waiting for e-mail verification. Sign in with a verification code to complete it.',
            ],
        ],
        'REG-08' => [
            'approver' => [
                'title' => 'Official e-mail change awaiting approval',
                'body' => 'The entity :entity (:reference) verified a new official e-mail address. Approve or reject the change.',
            ],
            'staff_applied' => [
                'title' => 'Entity official e-mail changed',
                'body' => 'The official e-mail of :entity (:reference) was changed to :email.',
            ],
            'entity' => [
                'title' => 'A change of your official e-mail was requested',
                'body' => 'A change of the official e-mail of :entity to :email was requested. If you did not request it, contact the platform administration.',
            ],
        ],
        'REG-09' => [
            'applied' => [
                'title' => 'Official e-mail changed',
                'body' => 'The official e-mail of :entity is now :email. Use it to sign in from now on.',
            ],
            'rejected' => [
                'title' => 'Official e-mail change rejected',
                'body' => 'The request to change the official e-mail of :entity to :email was rejected. The current e-mail stays in use.',
            ],
        ],
        'REG-10' => [
            'staff_deleted' => [
                'title' => 'Entity moved to Deleted entities',
                'body' => 'The entity :entity (:reference) was deleted, freezing :open_requests until it is restored.',
            ],
            'staff_restored' => [
                'title' => 'Entity restored',
                'body' => 'The entity :entity (:reference) was restored, and work resumed on :frozen_requests from where it stopped.',
            ],
            'entity' => [
                'title' => 'Your entity account was closed',
                'body' => 'The account of :entity on the Moashir platform was closed by the platform administration. Contact the administration for details.',
            ],
        ],
    ],
    'criteria' => [
        'scopes' => [
            'all' => 'all assessments',
            'new_only' => 'new requests only',
            'reset_open' => 'reset of the open requests',
        ],
        'STD-01' => [
            'title' => 'New main criterion',
            'body' => 'The main criterion :name was added to the draft criteria.',
        ],
        'STD-02' => [
            'title' => 'Main criterion weight changed',
            'body' => 'The weight of :name changed from :previous to :weight. Review the distribution of its sub-criteria.',
        ],
        'STD-03' => [
            'title' => 'New sub-criterion',
            'body' => 'The sub-criterion :name was added. Complete its weight and evaluation type.',
        ],
        'STD-04' => [
            'title' => 'Guiding-point evaluation enabled',
            'body' => 'The sub-criterion :name is now evaluated on guiding points. Define its guiding points.',
        ],
        'STD-05' => [
            'title' => 'Direct evaluation enabled',
            'body' => 'The sub-criterion :name is now evaluated directly. Review its guidance and attachments.',
        ],
        'STD-06' => [
            'title' => 'Assessment scale changed',
            'body' => 'Criteria version :version was published with a different assessment scale. The change affects new assessments only.',
        ],
        'STD-07' => [
            'title' => 'Criteria weights are inconsistent',
            'body' => 'Draft criteria version :version has :weight_problems in the weight distribution. Fix the weights before publishing.',
        ],
        'STD-08' => [
            'title' => 'Published criteria edited',
            'body' => 'The published criteria were edited with :changes, applied to :scope.',
        ],
    ],
    'assessments' => [
        'SELF-01' => [
            'title' => 'Self assessment started',
            'body' => 'Your self assessment :number was started. Your answers are saved as a draft until you submit it.',
        ],
        'SELF-02' => [
            'title' => 'Self assessment draft saved',
            'body' => 'The draft of your self assessment :number was saved. You can continue it at any time.',
        ],
        'SELF-03' => [
            'title' => 'Self assessment submitted',
            'body' => 'Your self assessment :number was submitted and its result was calculated.',
        ],
        'SELF-04' => [
            'title' => 'Self assessment result available',
            'body' => 'The result of your self assessment :number is available: :percent%. The self assessment is a measurement for your entity only.',
        ],
        'STD-08' => [
            'title' => 'Your self assessment draft was reset',
            'body' => 'The assessment criteria were updated and the scores of your self assessment draft :number were reset. Please score the items again.',
        ],
        'criteria_update_stalled' => [
            'title' => 'Criteria update of restored requests is still pending',
            'body' => 'The criteria update of the open requests of :entity (:reference) is still pending after :automatic_retries. Submission of these drafts stays blocked. Check that the queue worker is running.',
        ],
        'INT-05' => [
            'title' => 'Criteria update of restored requests failed',
            'body' => 'The criteria update of the open requests of :entity (:reference) did not complete after several attempts. The drafts stay on their current criteria version and can be submitted again (:count released). Review the technical log and retry the failed job.',
        ],
        'accredited_opening_failed' => [
            'title' => 'Accredited request could not be opened',
            'body' => 'The accredited assessment request of the paid payment :reference could not be opened because no criteria version is published. Publish the criteria; the request opens automatically within ten minutes.',
        ],
        'accredited' => [
            'ACC-01' => [
                'entity' => [
                    'title' => 'Your accredited assessment request is open',
                    'body' => 'Your payment was confirmed and the accredited assessment request :number is open. Fill in the CV entries and upload the evidence, then submit the request.',
                ],
            ],
            'ACC-02' => [
                'entity' => [
                    'title' => 'Accredited assessment draft saved',
                    'body' => 'The draft of your accredited assessment request :number was saved. You can continue it at any time.',
                ],
            ],
            'ACC-03' => [
                'entity' => [
                    'title' => 'Accredited assessment request submitted',
                    'body' => 'Your accredited assessment request :number was submitted. An assessor will be assigned to review it.',
                ],
                'staff' => [
                    'title' => 'New accredited assessment request',
                    'body' => 'The accredited assessment request :number of :entity was submitted and awaits the assignment of an assessor.',
                ],
            ],
            'ACC-04' => [
                'staff' => [
                    'title' => 'Request awaiting assignment',
                    'body' => 'The accredited assessment request :number of :entity awaits the assignment of an assessor.',
                ],
            ],
            'ACC-05' => [
                'assessor' => [
                    'title' => 'A request was assigned to you',
                    'body' => 'The accredited assessment request :number of :entity was assigned to you. Start the review from your assigned requests.',
                ],
                'staff' => [
                    'title' => 'Request assigned to an assessor',
                    'body' => 'The accredited assessment request :number of :entity was assigned to an assessor.',
                ],
                'released' => [
                    'title' => 'A request was reassigned',
                    'body' => 'The accredited assessment request :number of :entity was reassigned to another assessor. It no longer appears in your assigned requests.',
                ],
                'refunded' => [
                    'title' => 'Review of a request stopped',
                    'body' => 'The fee of the accredited assessment request :number of :entity was refunded and the request was closed. Your review of it has stopped.',
                ],
            ],
            'ACC-06' => [
                'entity' => [
                    'title' => 'The review of your request started',
                    'body' => 'An assessor started reviewing your accredited assessment request :number.',
                ],
                'staff' => [
                    'title' => 'Review started',
                    'body' => 'The assessor started reviewing the accredited assessment request :number of :entity.',
                ],
                'resubmitted' => [
                    'title' => 'Completed request returned for review',
                    'body' => ':entity completed the requested items of the accredited assessment request :number. The request is back under your review.',
                ],
            ],
            'ACC-07' => [
                'entity' => [
                    'title' => 'Completion of your request is required',
                    'body' => 'The assessor asked you to complete items of your accredited assessment request :number. Complete them and resubmit the request by :deadline (Riyadh time). :outcome',
                ],
                'outcomes' => [
                    'continue_as_submitted' => 'If the deadline passes, the review continues with the data already submitted.',
                    'decline' => 'If the deadline passes, the request is declined.',
                ],
            ],
            'ACC-08' => [
                'entity' => [
                    'title' => 'Your accredited assessment is complete',
                    'body' => 'The assessment of your accredited request :number is complete. Your result is :percent%.',
                ],
                'staff' => [
                    'title' => 'Accredited assessment completed',
                    'body' => 'The assessor completed the accredited assessment request :number of :entity with a result of :percent%.',
                ],
            ],
            'ACC-09' => [
                'entity' => [
                    'title' => 'Your accredited certificate was issued',
                    'body' => 'The certificate :certificate of your accredited assessment request :number was issued. You can download it from the certificates page.',
                ],
                'staff' => [
                    'title' => 'Accredited certificate issued',
                    'body' => 'The certificate :certificate was issued for the accredited assessment request :number of :entity.',
                ],
            ],
            'CERT-01' => [
                'entity' => [
                    'title' => 'Your certificate was revoked',
                    'body' => 'The certificate :certificate of your accredited assessment request :number was revoked. Reason: :reason',
                ],
                'staff' => [
                    'title' => 'Certificate revoked',
                    'body' => 'The certificate :certificate of :entity (request :number) was revoked.',
                ],
            ],
            'CERT-02' => [
                'entity' => [
                    'title' => 'Your certificate is under review',
                    'body' => 'The certificate :certificate of your accredited assessment request :number is suspended while the assessment is reviewed again. Its verification page shows "under review" until the review is completed and a new certificate is issued.',
                ],
                'staff' => [
                    'title' => 'Certificate suspended',
                    'body' => 'The certificate :certificate of :entity (request :number) was suspended because the assessment was reopened.',
                ],
            ],
            'ACC-10' => [
                'entity' => [
                    'title' => 'Your feedback report is available',
                    'body' => 'The feedback report of your accredited assessment request :number (strengths, improvement points and notes per sub-criterion) is available in the platform and can be downloaded as a PDF.',
                ],
            ],
            'ACC-11' => [
                'assessor' => [
                    'title' => 'Review past its due date',
                    'body' => 'The review of the accredited assessment request :number of :entity is past its due date. Complete it as soon as possible.',
                ],
                'staff' => [
                    'title' => 'Request past its due date',
                    'body' => 'The review of the accredited assessment request :number of :entity is past its due date. Follow up with the assessor or reassign the request.',
                ],
            ],
            'ACC-12' => [
                'continued' => [
                    'entity' => [
                        'title' => 'Completion deadline passed, the review continues',
                        'body' => 'The completion deadline of your accredited assessment request :number passed. As set by the assessor, the review continues with the data already submitted.',
                    ],
                    'assessor' => [
                        'title' => 'Completion deadline passed, the request is back under your review',
                        'body' => 'The completion deadline of the accredited assessment request :number of :entity passed. The request is back under your review with the data already submitted.',
                    ],
                    'staff' => [
                        'title' => 'Completion deadline passed, the review continues',
                        'body' => 'The completion deadline of the accredited assessment request :number of :entity passed. The request is back under review with the data already submitted.',
                    ],
                ],
                'declined' => [
                    'entity' => [
                        'title' => 'Your accredited assessment request was declined',
                        'body' => 'The completion deadline of your accredited assessment request :number passed without completion, so the request was declined as set by the assessor. Any refund is handled by the finance team outside the platform.',
                    ],
                    'assessor' => [
                        'title' => 'A request was declined after its completion deadline',
                        'body' => 'The completion deadline of the accredited assessment request :number of :entity passed without completion. The request was declined and your review of it has stopped.',
                    ],
                    'staff' => [
                        'title' => 'Request declined after its completion deadline',
                        'body' => 'The completion deadline of the accredited assessment request :number of :entity passed without completion. The request was declined as set by the assessor.',
                    ],
                ],
            ],
            'ACC-13' => [
                'entity' => [
                    'title' => 'Complete your accredited assessment request',
                    'body' => 'Accredited assessment request :number is paid and is still a draft. Complete the CVs and evidence and submit it so that it counts in its period.',
                ],
            ],
            'STD-08' => [
                'entity' => [
                    'title' => 'Your accredited assessment request was updated',
                    'body' => 'The assessment criteria were updated and the review of your accredited assessment request :number restarts on the new criteria. No action is needed from you unless the assessor asks for it.',
                ],
                'assessor' => [
                    'title' => 'Scores of a request were reset',
                    'body' => 'The assessment criteria were updated and the scores of the accredited assessment request :number of :entity were reset. Score the items again.',
                ],
            ],
            'EVD-01' => [
                'staff' => [
                    'title' => 'Evidence bundle ready',
                    'body' => 'The evidence bundle of the accredited assessment request :number of :entity is ready and contains :files. Download it from the request before it expires.',
                ],
            ],
            'EVD-02' => [
                'replace' => [
                    'title' => 'An evidence file was refused',
                    'body' => 'An evidence file of the sub-criterion :code in your accredited assessment request :number did not pass the security check and was refused. Remove it and upload a clean copy in its place.',
                ],
                'locked' => [
                    'title' => 'An evidence file was refused',
                    'body' => 'An evidence file of the sub-criterion :code in your accredited assessment request :number did not pass the security check and was refused. The assessor does not see it and may ask you to provide a clean copy.',
                ],
            ],
        ],
        'advanced' => [
            'ADV-03' => [
                'entity' => [
                    'title' => 'The entry conditions of the award cycle are not met',
                    'body' => 'Your application :number to the award cycle could not be accepted because your entity does not meet the entry conditions. Review the conditions on the award page.',
                ],
            ],
            'ADV-04' => [
                'entity' => [
                    'title' => 'Your award request was submitted',
                    'body' => 'Your award request :number was submitted. The Requests Follower reviews its completeness before the initial assessment.',
                ],
                'staff' => [
                    'title' => 'New award request',
                    'body' => 'The award request :number of :entity was submitted and awaits the completeness review and the assignment of an initial assessor.',
                ],
                'resubmitted' => [
                    'title' => 'Award request completed by the entity',
                    'body' => ':entity completed the requested items of the award request :number. The request is back in the queue for the assignment of an initial assessor.',
                ],
            ],
            'ADV-05' => [
                'assessor' => [
                    'title' => 'An award request was assigned to you',
                    'body' => 'The award request :number of :entity was assigned to you for the initial assessment. Start it from your assigned requests.',
                ],
                'released' => [
                    'title' => 'An award request was reassigned',
                    'body' => 'The award request :number of :entity was reassigned to another initial assessor. It no longer appears in your assigned requests.',
                ],
            ],
            'ADV-06' => [
                'staff' => [
                    'title' => 'Initial assessment completed',
                    'body' => 'The initial assessment of the award request :number of :entity is complete with a result of :percent%. It awaits the filtering decision.',
                ],
            ],
            'ADV-07' => [
                'staff' => [
                    'title' => 'Request qualified for the committee assessment',
                    'body' => 'The award request :number of :entity was qualified for the committee assessment (initial result :percent%, disqualification score :threshold%).',
                ],
            ],
            'ADV-08' => [
                'staff' => [
                    'title' => 'Request not qualified for the committee assessment',
                    'body' => 'The award request :number of :entity was not qualified for the committee assessment (initial result :percent%, disqualification score :threshold%).',
                ],
            ],
            'ADV-28' => [
                'staff' => [
                    'title' => 'Filtering exception below the disqualification score',
                    'body' => 'The award request :number of :entity was qualified for the committee assessment although its initial result of :percent% is below the disqualification score of :threshold%. Review the recorded reason.',
                ],
            ],
            'ADV-25' => [
                'entity' => [
                    'title' => 'Completion of your award request is required',
                    'body' => 'Your award request :number was returned to you to complete the listed items. Complete them and resubmit the request by :deadline (Riyadh time). :outcome',
                ],
                'outcomes' => [
                    'continue_as_submitted' => 'If the deadline passes, the request continues to the assessment as submitted.',
                    'decline' => 'If the deadline passes, the request is declined.',
                ],
            ],
            'ADV-27' => [
                'continued' => [
                    'entity' => [
                        'title' => 'Completion deadline passed, your request continues',
                        'body' => 'The completion deadline of your award request :number passed. As set when the request was returned, it continues to the assessment with the data already submitted.',
                    ],
                    'assessor' => [
                        'title' => 'Completion deadline passed',
                        'body' => 'The completion deadline of the award request :number of :entity passed. The request continues with the data already submitted.',
                    ],
                    'staff' => [
                        'title' => 'Completion deadline passed, the request continues',
                        'body' => 'The completion deadline of the award request :number of :entity passed. The request is back in the queue with the data already submitted.',
                    ],
                ],
                'declined' => [
                    'entity' => [
                        'title' => 'Your award request was declined',
                        'body' => 'The completion deadline of your award request :number passed without completion, so the request was declined as set when it was returned.',
                    ],
                    'assessor' => [
                        'title' => 'An award request was declined',
                        'body' => 'The completion deadline of the award request :number of :entity passed without completion and the request was declined.',
                    ],
                    'staff' => [
                        'title' => 'Award request declined after its completion deadline',
                        'body' => 'The completion deadline of the award request :number of :entity passed without completion. The request was declined as set when it was returned.',
                    ],
                ],
            ],
            'ADV-31' => [
                'entity' => [
                    'title' => 'Your award request was withdrawn',
                    'body' => 'Your award request :number was withdrawn at your request.',
                ],
                'staff' => [
                    'title' => 'Award request withdrawn',
                    'body' => 'The award request :number of :entity was withdrawn at the request of the entity.',
                ],
            ],
            'EVD-01' => [
                'staff' => [
                    'title' => 'Evidence bundle ready',
                    'body' => 'The evidence bundle of the award request :number of :entity is ready and contains :files. Download it from the request before it expires.',
                ],
            ],
            'EVD-02' => [
                'replace' => [
                    'title' => 'An evidence file was refused',
                    'body' => 'An evidence file of the sub-criterion :code in your award request :number did not pass the security check and was refused. Remove it and upload a clean copy in its place.',
                ],
                'locked' => [
                    'title' => 'An evidence file was refused',
                    'body' => 'An evidence file of the sub-criterion :code in your award request :number did not pass the security check and was refused. The assessor does not see it.',
                ],
            ],
        ],
    ],
    'periods' => [
        'PER-01' => [
            'entity' => [
                'title' => 'The accredited assessment period :period starts soon',
                'body' => 'The accredited assessment period :period runs from :starts_on to :ends_on. Submit your request within the period so that your assessment counts in it.',
            ],
        ],
        'PER-02' => [
            'draft' => [
                'entity' => [
                    'title' => 'The period :period ends in :days',
                    'body' => 'The accredited assessment period :period ends on :ends_on and your entity has not submitted yet. Complete draft request :number and submit it before the period ends.',
                ],
            ],
            'apply' => [
                'entity' => [
                    'title' => 'The period :period ends in :days',
                    'body' => 'The accredited assessment period :period ends on :ends_on and your entity has not submitted a request in it. Apply before the period ends; paying early is recommended.',
                ],
            ],
        ],
        'PER-03' => [
            'entity' => [
                'title' => 'The period :period ended without a request',
                'body' => 'The accredited assessment period :period ended without a request from your entity. You can apply in the current period.',
            ],
        ],
        'PER-04' => [
            'staff' => [
                'title' => 'Accredited assessment period :period digest',
                'body' => 'The period ends on :ends_on. Entities that have not submitted: :pending_entities. Paid requests still in draft: :paid_drafts. Transfers under review for entities whose period ends within 7 days: :pending_transfers.',
            ],
        ],
        'PER-05' => [
            'entity' => [
                'title' => 'Corrected accredited assessment period dates',
                'body' => 'The accredited assessment cadence changed, so the period :period you were told about no longer exists. The next period runs from :starts_on to :ends_on.',
            ],
        ],
    ],
    'award' => [
        'ADV-07' => [
            'entity' => [
                'title' => 'Your request was qualified for the committee assessment',
                'body' => 'Your award request :number in :cycle was qualified for the committee assessment after its initial assessment.',
            ],
        ],
        'ADV-08' => [
            'entity' => [
                'title' => 'Your request was not qualified for the committee assessment',
                'body' => 'Your award request :number in :cycle was not qualified for the committee assessment. Your feedback report based on the initial assessment is available.',
            ],
        ],
        'ADV-09' => [
            'assessor' => [
                'title' => 'New entities assigned to you for the committee assessment',
                'body' => 'You have been assigned the committee assessment of :entities from the award cycle :cycle. Assess each entity independently and submit before the committee end date.',
            ],
        ],
        'ADV-10' => [
            'staff' => [
                'title' => 'A committee assessor started an evaluation',
                'body' => ':assessor started the committee evaluation of the award request :number of :entity in :cycle.',
            ],
        ],
        'ADV-11' => [
            'staff' => [
                'title' => 'A committee assessor submitted an evaluation',
                'body' => ':assessor submitted the committee evaluation of the award request :number of :entity in :cycle.',
            ],
        ],
        'ADV-12' => [
            'staff' => [
                'title' => 'All committee assessors submitted',
                'body' => 'Every committee assessor submitted the evaluation of the award request :number of :entity in :cycle. The committee average is :average%. Approve it or return the request to the committee.',
            ],
        ],
        'ADV-13' => [
            'assessor' => [
                'title' => 'Committee evaluations are overdue',
                'body' => 'The committee assessment of :cycle ended on :committee_end (Riyadh time) and you still have :open_evaluations. Complete and submit what remains.',
            ],
            'staff' => [
                'title' => 'A committee assessor is overdue',
                'body' => 'The committee assessment of :cycle ended on :committee_end (Riyadh time) and :assessor still has :open_evaluations.',
            ],
        ],
        'ADV-14' => [
            'staff' => [
                'title' => 'A committee assessor was disabled',
                'body' => 'The committee assessor :assessor was disabled in :cycle. Exclude their scores or appoint a substitute committee assessor.',
            ],
        ],
        'ADV-15' => [
            'assessor' => [
                'title' => 'You were appointed as a substitute committee assessor',
                'body' => 'You were appointed as a substitute committee assessor in :cycle. You can read the scores and notes of the assessor you replace read only; complete and submit your own evaluations.',
            ],
            'staff' => [
                'title' => 'A substitute committee assessor was appointed',
                'body' => ':assessor was appointed in :cycle as the substitute of :replaced. Their results need your approval before the replaced scores leave the average.',
            ],
        ],
        'ADV-16' => [
            'assessor' => [
                'title' => 'A request was returned to the committee',
                'body' => 'The Award Secretary returned the request :label in :cycle to the committee to amend your evaluation. Note: :note',
            ],
        ],
        'ADV-17' => [
            'staff' => [
                'title' => 'Committee average approved',
                'body' => 'The Award Secretary approved the committee average of :average% for the award request :number of :entity in :cycle.',
            ],
            'assessor' => [
                'title' => 'Committee result approved',
                'body' => 'The Award Secretary approved the committee result of the request :label in :cycle.',
            ],
        ],
        'ADV-18' => [
            'entity' => [
                'title' => 'The award results are announced',
                'body' => 'The results of :cycle are announced. View the result of your award request :number and its feedback report.',
            ],
            'staff' => [
                'title' => 'Award results announced',
                'body' => 'The Award Secretary announced the results of :cycle.',
            ],
        ],
        'ADV-20' => [
            'staff' => [
                'title' => 'Regional results approved',
                'body' => 'The results of the region :region in :cycle were approved with :winners and are locked.',
            ],
        ],
        'ADV-21' => [
            'staff' => [
                'title' => 'Entities qualified for the national award',
                'body' => ':winners from :region in :cycle qualified for the national award. Set the national ranks.',
            ],
        ],
        'ADV-22' => [
            'staff' => [
                'title' => 'National result approved',
                'body' => 'The national result of :cycle was approved and is locked. The results can now be announced.',
            ],
        ],
        'ADV-23' => [
            'entity_winner' => [
                'title' => 'Your regional award result',
                'body' => 'Congratulations: your award request :number won rank :rank in its region in :cycle.',
            ],
            'entity_non_winner' => [
                'title' => 'Your regional award result',
                'body' => 'Your award request :number was not among the regional winners of :cycle. Thank you for taking part.',
            ],
        ],
        'ADV-24' => [
            'entity_winner' => [
                'title' => 'Your national award result',
                'body' => 'Congratulations: your award request :number won rank :rank in the national award of :cycle.',
            ],
            'entity_non_winner' => [
                'title' => 'Your national award result',
                'body' => 'Your award request :number qualified for the national award of :cycle but was not among the national winners.',
            ],
        ],
        'ADV-26' => [
            'staff' => [
                'title' => 'Substitute committee assessor result approved',
                'body' => 'The result of the substitute committee assessor :assessor for the award request :number of :entity in :cycle was approved. The replaced scores left the committee average.',
            ],
        ],
        'ADV-29' => [
            'session' => [
                'title' => 'A committee discussion was opened',
                'body' => 'The Award Secretary opened a committee discussion on the request :label in :cycle. Write your opinion before :deadline (Riyadh time).',
            ],
            'agreed_outcome' => [
                'title' => 'An agreed committee outcome awaits your response',
                'body' => 'The Award Secretary entered the agreed committee outcome of the request :label in :cycle. Approve it or request an edit with your reason.',
            ],
        ],
        'ADV-30' => [
            'opinion' => [
                'title' => 'A committee assessor wrote an opinion',
                'body' => 'A committee assessor wrote an opinion in the discussion of the award request :number of :entity in :cycle.',
            ],
            'approve' => [
                'title' => 'A committee assessor approved the agreed outcome',
                'body' => 'A committee assessor approved the agreed outcome of the award request :number of :entity in :cycle.',
            ],
            'request_edit' => [
                'title' => 'A committee assessor requested an edit',
                'body' => 'A committee assessor requested an edit of the agreed outcome of the award request :number of :entity in :cycle. Review the reason.',
            ],
        ],
        'ADV-01' => [
            'entity' => [
                'title' => 'A new award cycle is open',
                'body' => 'The award cycle :cycle is open for applications until :submission_end (Riyadh time). Check the entry conditions and apply from the award page.',
            ],
            'staff' => [
                'title' => 'Award cycle opened',
                'body' => 'The award cycle :cycle is open for applications until :submission_end (Riyadh time).',
            ],
        ],
        'ADV-02' => [
            'entity' => [
                'title' => 'The award submission deadline is approaching',
                'body' => 'Your award request :number is still a draft. The submission period of :cycle ends on :submission_end (Riyadh time). Complete and submit it before then.',
            ],
        ],
        'CYC-01' => [
            'staff' => [
                'title' => 'Award submissions closed',
                'body' => 'The submission period of the award cycle :cycle has closed. Drafts that were not submitted have expired.',
            ],
        ],
        'CYC-02' => [
            'staff' => [
                'title' => 'Award cycle closed',
                'body' => 'The award cycle :cycle was closed.',
            ],
        ],
    ],
    'integrations' => [
        'INT-01' => [
            'title' => 'SMS sending failed',
            'body' => 'An SMS could not be sent through :provider (:error). Review the SMS settings and the provider account.',
        ],
        'INT-02' => [
            'title' => 'SMS gateway test passed',
            'body' => 'The connection test of the SMS provider :provider passed.',
        ],
        'INT-03' => [
            'title' => 'Payment gateway test failed',
            'body' => 'The connection test of the payment gateway failed (:error). Review the payment settings.',
        ],
        'INT-04' => [
            'title' => 'External service unavailable',
            'body' => 'An external service used by the platform is not configured or not reachable. Review the integration settings.',
        ],
        'INT-06' => [
            'title' => 'Backup check failed',
            'body' => 'The daily backup check found a missing, outdated or failed backup. Ask the operations team to verify the backups today.',
        ],
        'AI-04' => [
            'title' => 'AI provider settings updated',
            'body' => 'The AI provider, model or API key was changed. Run a connection test.',
        ],
        'AI-05' => [
            'title' => 'AI provider connection failed',
            'body' => 'The connection to the AI provider :provider failed (:error). Review the AI settings.',
        ],
    ],
    'payments' => [
        'PAY-01' => [
            'entity' => [
                'title' => 'Payment order created',
                'body' => 'The payment order :reference for :purpose (:amount) was created. Complete the payment before the order expires.',
            ],
        ],
        'PAY-02' => [
            'entity' => [
                'title' => 'Payment completed',
                'body' => 'Your payment :reference for :purpose (:amount) was completed successfully.',
            ],
            'staff' => [
                'title' => 'Payment received',
                'body' => ':entity paid :amount for :purpose (payment :reference).',
            ],
        ],
        'PAY-03' => [
            'entity' => [
                'title' => 'Payment not completed',
                'body' => 'The payment :reference for :purpose was not completed. You can start a new payment at any time.',
            ],
            'staff' => [
                'title' => 'Payment failed',
                'body' => 'The payment :reference of :entity for :purpose failed. Check the payment gateway if failures repeat.',
            ],
        ],
        'PAY-04' => [
            'entity' => [
                'title' => 'Refund recorded',
                'body' => 'A refund of :amount for the payment :reference (:purpose) was recorded. Its payment receipt was voided.',
            ],
            'staff' => [
                'title' => 'Refund recorded',
                'body' => 'A refund of :amount was recorded for the payment :reference of :entity (:purpose). The receipt was voided.',
            ],
        ],
        'PAY-06' => [
            'entity' => [
                'title' => 'Payment receipt issued',
                'body' => 'The payment receipt of your payment :reference (:amount) is available in My Payments.',
            ],
            'staff' => [
                'title' => 'Payment receipt issued',
                'body' => 'A payment receipt was issued for the payment :reference of :entity (:amount).',
            ],
        ],
        'PAY-07' => [
            'entity' => [
                'title' => 'Payment order expired',
                'body' => 'The payment order :reference for :purpose expired before the payment was completed. You can start a new payment.',
            ],
        ],
        'PAY-08' => [
            'staff' => [
                'title' => 'Payment to refund outside the platform',
                'body' => 'The gateway confirmed :amount for the payment :reference of :entity, but the order could not be completed (it had expired or the amount did not match). Return the amount outside the platform and record the refund.',
            ],
            'staff_transfer' => [
                'title' => 'Bank transfer to return outside the platform',
                'body' => 'Payment request :reference of :entity (:amount) was cancelled after the entity data was permanently erased while its bank transfer receipt was under review. Return the amount outside the platform if it reached the account.',
            ],
        ],
        'PAY-09' => [
            'staff' => [
                'title' => 'Refund reported by the gateway',
                'body' => 'The payment gateway reported a refund or a void of the payment :reference of :entity. Record the refund with its bank-transfer receipt.',
            ],
        ],
        'PAY-11' => [
            'entity' => [
                'title' => 'Bank transfer receipt received',
                'body' => 'We received the bank transfer receipt for payment request :reference for :purpose (:amount). The finance team is reviewing it, and you will be notified of the result.',
            ],
            'staff' => [
                'title' => 'Bank transfer awaiting approval',
                'body' => ':entity attached a bank transfer receipt for payment request :reference for :purpose (:amount). Review the receipt, then approve or reject it.',
            ],
        ],
        'PAY-12' => [
            'entity' => [
                'title' => 'Bank transfer not approved',
                'body' => 'The bank transfer receipt for payment request :reference for :purpose was not approved. Reason: :reason. Submit a new payment request for the service.',
                'note' => 'Finance team note: :note',
            ],
        ],
        'FIN-01' => [
            'title' => 'Transactions export ready',
            'body' => 'Your export of :transactions is ready to download.',
        ],
        'FIN-03' => [
            'title' => 'Bank transfer accounts changed',
            'body' => 'The :bank bank account ending in :ending was :change. Check that the change is authorised.',
        ],
        'issues' => [
            'verification_rejected' => [
                'title' => 'Payment verification rejected',
                'body' => 'A payment returned by the gateway did not match the order it was presented for. Review the payment operations log.',
            ],
            'unexpected_status' => [
                'title' => 'Unexpected payment status',
                'body' => 'The payment gateway reported a status that the platform does not use (for example authorized or captured). Review the payment operations log and the gateway account.',
            ],
            'unmatched_live_webhook' => [
                'title' => 'Unmatched live payment notification',
                'body' => 'A live payment notification from the gateway did not match any payment order. Review the payment operations log.',
            ],
        ],
    ],
    'files' => [
        'quarantined' => [
            'title' => 'Infected file quarantined',
            'body' => 'The background malware scan found an infected file (:purpose, file :file). The file was moved to quarantine and is never offered for download. Review the security log.',
        ],
        'infected_upload' => [
            'title' => 'Infected upload refused',
            'body' => 'The malware scan found an infected file uploaded as :purpose. The upload was refused and the file was removed before any use. Further infected uploads of the same kind within the next minutes are recorded in the audit log without a new alert. Review the audit log.',
        ],
        'scan_abandoned' => [
            'title' => 'Uploaded files could not be scanned',
            'body' => 'Uploaded files could not be scanned for malware after several attempts because the scanner did not respond or a file could not be read. These files stay unavailable. Check the ClamAV service and the technical log.',
        ],
        'purposes' => [
            'refund_evidence' => 'bank-transfer receipt of a refund',
            'apple_pay_domain_association' => 'Apple Pay domain association file',
            'receipt_pdf' => 'payment receipt document',
            'finance_export' => 'financial transactions export',
            'assessment_evidence' => 'assessment evidence file',
            'evidence_bundle' => 'evidence bundle',
            'cms_image' => 'public site image',
            'kpi_image' => 'indicator image',
            'branding_logo' => 'back-office logo',
        ],
    ],
    'content' => [
        'site_settings' => 'the site settings',
        'pages' => [
            'home' => 'the homepage',
            'accredited_intro' => 'the accredited assessment page',
            'advanced_intro' => 'the advanced assessment page',
            'bag_details' => 'the Quick Excellence Kit page',
            'lenses' => 'the analytical lenses page',
            'kpis' => 'the KPIs page',
            'accessibility' => 'the accessibility statement page',
        ],
        'CMS-01' => [
            'title' => 'New indicator added',
            'body' => 'The indicator :name was added to the KPIs page.',
        ],
        'CMS-02' => [
            'title' => 'Indicator value updated',
            'body' => 'The value of the indicator :name was updated.',
        ],
        'CMS-03' => [
            'title' => 'Indicator published',
            'body' => 'The indicator :name is now published on the KPIs page.',
        ],
        'CMS-04' => [
            'title' => 'Public content updated',
            'body' => 'Changes to :page are now visible on the public site.',
        ],
        'CMS-05' => [
            'title' => 'Content unpublished',
            'body' => ':content was removed from the public site.',
        ],
    ],
    'contact' => [
        'open_messages' => 'Open my messages',
        'acknowledgement' => [
            'subject' => 'We received your message :reference',
            'received' => 'Thank you for contacting the Moashir platform. Your message about ":type" was received with the reference :reference.',
            'next' => 'Our team will review it and reply to this e-mail address. Keep the reference for any follow-up.',
        ],
        'reply' => [
            'subject' => 'Reply to your message :reference',
            'intro' => 'The Moashir team replied to your message :reference:',
            'guest_follow_up' => 'To follow up, send a new message through the contact form on the Moashir platform and mention the reference :reference.',
        ],
        'CNT-01' => [
            'title' => 'New contact message',
            'body' => 'A new message :reference about ":type" was received through the contact form.',
        ],
        'CNT-02' => [
            'title' => 'Reply to your message',
            'body' => 'The Moashir team replied to your message :reference. Open it to read the reply.',
        ],
        'CNT-04' => [
            'title' => 'Sender replied to a contact message',
            'body' => 'The sender of the message :reference about ":type" added a reply.',
        ],
        'CNT-05' => [
            'title' => 'Contact message assigned to you',
            'body' => 'The message :reference about ":type" was assigned to you.',
        ],
    ],
];
