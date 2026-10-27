import { fieldLabel } from './apiValidation'

export interface ApiError {
  code?: string
  message?: string
  /** Machine-readable codes keyed by field (the API error contract). */
  errors?: Record<string, string[] | string>
  /** Human, backend-localized text keyed by the same fields. */
  messages?: Record<string, string[] | string>
  status?: number
  handled?: boolean
  success?: boolean
}

/** The first entry of a field value that may be a string or a list of strings. */
const firstFieldValue = (value: string[] | string | undefined): string | undefined =>
  Array.isArray(value) ? value[0] : value

/** Backend codes always live in a known namespace; anything else is already human. */
const isErrorCode = (value: string) => value.startsWith('validation.') || value.startsWith('business.')

const errorMessages: Record<string, string> = {
  'validation.required': 'This field is required.',
  'validation.accepted': 'You must accept this value.',
  'validation.array': 'Enter a valid list.',
  'validation.before_or_equal': 'Choose a date that is not in the future.',
  'validation.between': 'Enter a value within the allowed range.',
  'validation.boolean': 'Choose a valid option.',
  'validation.confirmed': 'The confirmation does not match.',
  'validation.date': 'Enter a valid date.',
  'validation.date_format': 'Use the required date format.',
  'validation.digits': 'Enter the required number of digits.',
  'validation.distinct': 'Each selected value must be unique.',
  'validation.email': 'Enter a valid email address.',
  'validation.exists': 'Select an available option.',
  'validation.file': 'Upload a valid file.',
  'validation.image': 'Upload a valid image.',
  'validation.in': 'Select one of the available options.',
  'validation.integer': 'Enter a whole number.',
  'validation.max': 'The value exceeds the allowed limit.',
  'validation.mimes': 'Upload a supported file type.',
  'validation.min': 'The value is below the allowed minimum.',
  'validation.numeric': 'Enter a number.',
  'validation.present': 'This field must be included.',
  'validation.format': 'Use the required format.',
  'validation.same': 'The values must match.',
  'validation.string': 'Enter text.',
  'validation.unique': 'This value is already in use.',
  'validation.uploaded': 'The upload failed. Please try again.',
  'validation.invalid': 'Enter a valid value.',
  'validation_failed': 'Please correct the highlighted fields.',
  'business.contract_version_scope_required': 'Choose the destination country or the test this contract version applies to.',
  'business.currency_rate_invalid': 'Select a currency with a positive exchange rate.',
  'business.service_currency_rate_invalid': 'One or more selected services have an invalid currency rate.',
  'business.employee_not_selectable': 'This employee is not eligible for this action.',
  'business.attendance_already_recorded': 'Attendance has already been recorded for this date.',
  'business.deduction_not_owned': 'One or more deductions do not belong to this salary payment.',
  'business.employee_has_no_account': 'The selected employee does not have a system account.',
  'business.conflict': 'This action conflicts with the current data. Refresh and try again.',
  'business.request_rejected': 'This action is not allowed in the current state.',
  'business.support_recipient_not_configured': 'Support email is not configured yet. Ask an administrator to set SUPPORT_TICKET_RECIPIENT.',
  'business.support_email_delivery_failed': 'Your support request could not be delivered. Nothing was submitted; please try again shortly.',
  'business.reset_token_invalid': 'The password reset link is invalid or has expired.',
  'business.board_order_invalid': 'The board order is no longer valid. Refresh and try again.',
  'business.task_assignee_outside_department': 'As a department manager you can only assign tasks to members of your own department.',
  'business.cannot_delete_current_user': 'You cannot delete your own account.',
  'business.last_super_admin_required': 'At least one active super administrator must remain.',
  'business.cannot_remove_current_super_admin_role': 'You cannot remove your own super administrator role.',
  'business.audit_already_reviewed': 'This audit entry has already been reviewed.',
  'business.audit_not_latest_change': 'Only the latest change to this record can be discarded.',
  'business.audit_row_not_deleted': 'This record is not currently deleted.',
  'business.audit_row_deleted': 'Restore the deleted record before discarding this edit.',
  'business.audit_event_not_revertible': 'This audit entry cannot be reverted.',
  'business.audit_model_invalid': 'This audit entry refers to an invalid record type.',
  'business.leave_request_limit_reached': 'Your off-day allowance for this year has been used.',
  'business.leave_request_conflicts_with_attendance': 'This day already has recorded work. Remove the work entry before approving a full-day request.',
  'business.current_password_invalid': 'The current password is incorrect.',
  'business.account_not_active': 'Only an active account can request deactivation.',
  'business.account_not_deactivated': 'This account is not currently deactivated.',
  'business.deactivation_request_pending': 'A deactivation request is already awaiting review.',
  'business.deactivation_request_not_pending': 'This deactivation request has already been completed or cancelled.',
  'business.no_eligible_deactivation_reviewer': 'No eligible higher-authority reviewer is currently available.',
  'business.reviewer_rank_insufficient': 'Your authority level is not high enough to review this account.',
  'business.use_account_reactivation_workflow': 'Use the audited account reactivation workflow for this user.',
  'business.edit_window_expired': 'This item can only be edited during the first hour after creation.',
  'business.salary_currency_immutable': 'A salary currency cannot be changed after the payment record is created.',
  'business.granted_bonus_immutable': 'A granted bonus is an immutable financial record.',
  'business.granted_bonus_cannot_be_reversed': 'A granted bonus cannot be returned to pending status.',
  'business.bonus_already_in_salary': 'This bonus is already included in a salary payment.',
  'business.bonus_currency_snapshot_invalid': 'The bonus does not have a valid currency snapshot.',
  'business.workday_attendance_conflict': 'Your attendance is already marked with a status that prevents starting the day.',
  'business.workday_already_ended': 'This workday has already ended.',
  'business.workday_already_started': 'End your active workday before starting another one.',
  'business.workday_not_started': 'Start your workday before ending it.',
  'business.daily_report_already_submitted': 'Your report for this workday has already been submitted.',
  'business.office_geofence_unavailable': 'Automatic attendance is unavailable because your office geofence is not configured.',
  'business.leave_request_date_exists': 'An off-day request already exists for one or more selected dates.',
  'business.task_label_exists': 'A task label with this name already exists.',
  'business.task_bonus_review_requires_completion': 'Only completed tasks can receive bonus review inputs.',
  'business.task_contributions_must_total_100': 'Contribution percentages must total 100%.',
  'business.task_contributions_assignees_mismatch': 'Provide one contribution percentage for every assigned user.',
  'business.task_contribution_user_not_assigned': 'Every contribution user must be assigned to the task.',
  'business.employee_profile_missing': 'No employee profile is associated with this account.',
  'business.two_factor_code_invalid': 'The authentication code is invalid or expired.',
  'business.position_in_use': 'This position is assigned to employees or users and cannot be deleted.',
  'business.office_has_asset': 'This office has asset assigned to it and cannot be deleted.',
  'business.contract_template_in_use': 'Templates used by contracts cannot be deleted.',
  'business.contract_not_renewable': 'Only active or expired contracts can be continued as a renewal.',
  'business.contract_template_unavailable': 'The source contract template is no longer available. Create a new contract from an active template.',
  'business.contract_renewal_exists': 'This contract already has a current renewal. Review that renewal instead of creating a duplicate.',
  'business.base_currency_cannot_be_deleted': 'The base currency cannot be deleted.',
  'business.currency_in_use': 'Currencies referenced by contract lines, salaries, incomes, or expenses cannot be deleted.',
  'auth.email_verification_required': 'Verify your email address before signing in.',
  'auth.two_factor_required': 'Enter your current authenticator code.',
  'auth.refresh_token_invalid': 'Your session renewal has expired. Please sign in again.',
  'auth.password_setup_required': 'Create your password before signing in.',
  'auth.unauthenticated': 'Your session has ended. Please sign in again.',
  'auth.invalid_credentials': 'The email address or password is incorrect.',
  'auth.account_inactive': 'This account is not active.',
  'auth.forbidden': 'You do not have permission to perform this action.',
  'resource.not_found': 'The requested record is no longer available.',
  'request.rate_limited': 'Too many requests. Please wait and try again.',
  'server.error': 'Something went wrong. Please try again.',
}

export const humanizeApiError = (code?: string, fallback = translate('common.messages.unableToSaveChanges')): string => {
  if (!code)
    return fallback
  const translated = translate(`backend.errors.${code}`)

  // Error codes are the API contract. Do not fall back to an English literal
  // here: a missing catalog entry should use the caller's localized fallback.
  if (translated && !translated.startsWith('[missing:'))
    return translated

  // An unmapped validation rule should still read as a validation problem
  // instead of leaking the machine code (e.g. "validation.alpha_dash").
  if (code.startsWith('validation.')) {
    const generic = translate('backend.errors.validation.invalid')
    if (generic && !generic.startsWith('[missing:'))
      return generic
  }

  return fallback
}

/**
 * Per-field error text for inline validation feedback. The backend already
 * localizes the real validation message (including rule limits such as "must be
 * between 1 and 65535"), so prefer it and only translate the machine code when
 * no message was sent.
 */
export const apiFieldErrors = (error: unknown): Record<string, string> => {
  const errors = (error as ApiError | undefined)?.errors
  if (!errors)
    return {}
  const messages = (error as ApiError | undefined)?.messages

  return Object.fromEntries(Object.entries(errors).map(([field, value]) => {
    const message = firstFieldValue(value)
    if (!message)
      return [field, '']

    return [
      field,
      isErrorCode(message)
        ? firstFieldValue(messages?.[field]) || humanizeApiError(message)
        : message,
    ]
  }))
}

/**
 * The single message shown in a toast: "Field name: human explanation". The
 * label is dropped when it cannot be resolved so the user never sees a
 * dangling ": message".
 */
export const apiErrorMessage = (error: unknown, fallback = translate('common.messages.unableToSaveChanges')): string => {
  const apiError = error as ApiError | undefined
  const [field, value] = Object.entries(apiError?.errors ?? {})[0] ?? []
  const code = firstFieldValue(value)

  if (field && code) {
    const label = fieldLabel(field)
    const text = apiFieldErrors(apiError)[field] || humanizeApiError(code)

    return label ? `${label}: ${text}` : text
  }

  return humanizeApiError(apiError?.code, apiError?.message || fallback)
}
