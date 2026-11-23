<?php

/*
|--------------------------------------------------------------------------
| SCMS Interdepartmental Coordination & PCO Department Approval Config
|--------------------------------------------------------------------------
|
| Configuration for:
| 1. PCO (Property Custodian Office) department identification.
| 2. Eligible approving departments and academic units for PCO work requests.
|    - Fails closed if both eligible_approving_departments and
|      eligible_approving_academic_units are empty arrays.
|    - Do not assume every operational department or academic unit is
|      authorized to approve PCO work unless explicitly listed or configured.
|
*/

return [

    'pco' => [
        // Official department label in SCMS until full institutional name is confirmed.
        'department_name' => env('SCMS_PCO_DEPARTMENT_NAME', 'PCO'),

        // Configurable list of operational department names (or IDs) authorized to approve PCO work.
        // Fails closed if empty. By default, administrative/financial/security oversight offices are eligible,
        // and tests or institutional configuration can override or expand this list via config('scms.pco.eligible_approving_departments').
        'eligible_approving_departments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SCMS_PCO_ELIGIBLE_APPROVING_DEPARTMENTS',
                "Campus Director's Office,Maritime Superintendent Office,Student's Accounting,Accounting,Cashier,CSU,CSU ( Civil Security Unit ),SAO,SAO ( Student Affairs Office ),EDP,EDP ( Electronics Data Processing )"
            ))
        ))),

        // Configurable list of academic unit names (or IDs) authorized to approve PCO work.
        // Defaults to empty (fails closed for academic units unless explicitly configured).
        'eligible_approving_academic_units' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SCMS_PCO_ELIGIBLE_APPROVING_ACADEMIC_UNITS', ''))
        ))),

        // Roles within an eligible approving department/unit permitted to record an approval decision.
        'approver_roles' => ['dept_staff', 'sao', 'dean'],
    ],

];
