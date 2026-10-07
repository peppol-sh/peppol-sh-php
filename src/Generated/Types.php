<?php

declare(strict_types=1);

// Generated from apps/api/src/openapi.yaml by scripts/generate-types.ts. Do not edit.

namespace PeppolSh\Generated;

/**
 * Array shapes of the peppol.sh API, for PHPStan and for IDE completion.
 * There is one alias for each schema of the OpenAPI spec. Each operation has
 * `<Operation>Params` (JSON request body), `<Operation>Query` (query
 * parameters), and `<Operation>Response` (2xx response) where they apply.
 *
 * To use an alias in your code, import it in the docblock of your class with
 * the PHPStan tag `phpstan-import-type <Alias> from \PeppolSh\Generated\Types`.
 *
 * The API can add fields at any time: a shape lists the known keys only.
 *
 * @phpstan-type AuditEvent array{
 *   id: string,
 *   actor_account_id?: string|null,
 *   workspace_id?: string|null,
 *   event_type: 'api_key.created'|'api_key.revoked'|'company.created'|'company.updated'|'membership.invited'|'membership.removed'|'membership.role_changed'|'membership.ownership_transferred'|'webhook.created'|'webhook.deleted'|'workspace.created',
 *   subject_type?: 'api_key'|'company'|'membership'|'webhook'|'workspace'|null,
 *   subject_id?: string|null,
 *   ip?: string|null,
 *   user_agent?: string|null,
 *   metadata?: string|null,
 *   created_at: string,
 * }
 *
 * @phpstan-type HealthResponse array{
 *   status: 'ok'|'degraded',
 *   version: string,
 *   environment: 'production'|'staging'|'development'|'test'|'unknown',
 *   checks: array{
 *     db: 'ok'|'error',
 *   },
 * }
 *
 * @phpstan-type Workspace array{
 *   id?: string,
 *   name?: string,
 *   connect_enabled?: bool,
 *   credit_balance?: int,
 *   suspended?: bool,
 *   role?: 'owner'|'admin'|'member',
 *   created_at?: string,
 *   updated_at?: string,
 * }
 *
 * @phpstan-type WorkspaceMember array{
 *   account_id?: string,
 *   email?: string,
 *   name?: string|null,
 *   role?: 'owner'|'admin'|'member',
 *   created_at?: string,
 * }
 *
 * @phpstan-type SignupResponse array{
 *   id?: string,
 *   email?: string,
 *   status?: 'active',
 *   api_key?: string,
 * }
 *
 * @phpstan-type Account array{
 *   id?: string,
 *   email?: string,
 *   name?: string|null,
 *   status?: 'active'|'invited'|'disabled',
 *   api_keys?: list<array{
 *     prefix?: string,
 *     sandbox?: bool,
 *     label?: string|null,
 *     last_used_at?: string|null,
 *     created_at?: string,
 *     revoked?: bool,
 *   }>,
 *   usage?: array{
 *     total_documents_sent?: int,
 *     total_api_calls?: int,
 *   },
 *   created_at?: string,
 * }
 *
 * @phpstan-type ApiKeyCreated array{
 *   api_key?: string,
 *   prefix?: string,
 *   sandbox?: bool,
 *   label?: string|null,
 * }
 *
 * @phpstan-type UsageResponse array{
 *   period?: array{
 *     days?: int,
 *     since?: string,
 *   },
 *   totals?: array{
 *     documents_sent?: int,
 *     api_calls?: int,
 *   },
 *   daily?: list<array{
 *     date?: string,
 *     documents_sent?: int,
 *     api_calls?: int,
 *   }>,
 * }
 *
 * @phpstan-type CompanyCreate array{
 *   name: string,
 *   country: string,
 *   company_registration_id?: string,
 *   tax_id?: string,
 *   email?: string,
 *   iban?: string,
 *   peppol_id?: string,
 *   address?: array{
 *     street?: string,
 *     city?: string,
 *     postal_code?: string,
 *   },
 * }
 *
 * @phpstan-type CompanyUpdate array{
 *   name?: string,
 *   company_registration_id?: string,
 *   tax_id?: string,
 *   email?: string,
 *   country?: string,
 *   iban?: string,
 *   peppol_id?: string,
 *   address?: array{
 *     street?: string,
 *     city?: string,
 *     postal_code?: string,
 *   },
 * }
 *
 * @phpstan-type Company array{
 *   id?: string,
 *   name?: string,
 *   workspace_id?: string,
 *   role?: 'owner'|'admin'|'member',
 *   is_live?: bool,
 *   company_registration_id?: string|null,
 *   tax_id?: string|null,
 *   email?: string|null,
 *   country?: string,
 *   address?: array{
 *     street?: string|null,
 *     city?: string|null,
 *     postal_code?: string|null,
 *   }|null,
 *   iban?: string|null,
 *   peppol_id?: string|null,
 *   created_at?: string,
 * }
 *
 * @phpstan-type CompanyListItem array{
 *   id?: string,
 *   name?: string,
 *   tax_id?: string|null,
 *   country?: string|null,
 *   is_live?: bool,
 *   workspace_id?: string,
 *   created_at?: string,
 * }
 *
 * @phpstan-type CompanyDetail array{
 *   id?: string,
 *   name?: string,
 *   workspace_id?: string,
 *   company_registration_id?: string|null,
 *   tax_id?: string|null,
 *   email?: string|null,
 *   country?: string,
 *   address?: array{
 *     street?: string|null,
 *     city?: string|null,
 *     postal_code?: string|null,
 *   },
 *   iban?: string|null,
 *   peppol_id?: string|null,
 *   is_live?: bool,
 *   created_at?: string,
 *   updated_at?: string,
 * }
 *
 * @phpstan-type KycState array{
 *   status: 'none'|'pending'|'approved'|'rejected',
 *   company_id?: string|null,
 *   submitted_at?: string|null,
 *   reviewed_at?: string|null,
 *   reject_reason?: string|null,
 *   review_reference?: string|null,
 *   legal_name?: string|null,
 *   enterprise_number?: string|null,
 *   attestation: KycAttestation,
 *   documents: list<KycDocument>,
 *   mandate_coverage: KycMandateCoverage,
 *   requirements: KycRequirements,
 * }
 *
 * @phpstan-type KycAttestation array{
 *   name?: string|null,
 *   role?: string|null,
 *   version?: string|null,
 *   current_version: string,
 *   text: string,
 * }
 *
 * @phpstan-type KycMandateCoverage array{
 *   companies_with_mandate: int,
 *   total_companies: int,
 * }
 *
 * @phpstan-type KycRequirements array{
 *   has_company: bool,
 *   own_company_id?: string|null,
 *   missing_workspace_doc_types: list<'registry_extract'|'representative_id'|'authority_proof'>,
 *   can_submit: bool,
 * }
 *
 * @phpstan-type KycDocument array{
 *   id: string,
 *   company_id?: string|null,
 *   doc_type: 'registry_extract'|'representative_id'|'authority_proof'|'mandate',
 *   filename: string,
 *   mime_type: 'application/pdf'|'image/png'|'image/jpeg'|'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
 *   size_bytes: int,
 *   mandate_grantor_name?: string|null,
 *   created_at: string,
 * }
 *
 * @phpstan-type KycDocumentUpload array{
 *   doc_type: 'registry_extract'|'representative_id'|'authority_proof'|'mandate',
 *   content: string,
 *   filename?: string,
 *   mime_type?: string,
 *   company_id?: string,
 *   mandate_grantor_name?: string,
 * }
 *
 * @phpstan-type KycSubmit array{
 *   company_id: string,
 *   kyc_legal_name: string,
 *   kyc_enterprise_number: string,
 *   attestation_name: string,
 *   attestation_role: string,
 *   attested: bool,
 *   attestation_version: string,
 * }
 *
 * @phpstan-type DocumentCreate array{
 *   type?: 'invoice'|'credit_note',
 *   number: string,
 *   issue_date: string,
 *   due_date?: string,
 *   currency?: string,
 *   from: Party,
 *   to: Party,
 *   lines: list<LineItem>,
 *   note?: string,
 *   payment_means?: PaymentMeans,
 *   attachments?: list<Attachment>,
 *   idempotency_key?: string,
 *   buyer_reference?: string,
 *   allowances?: list<AllowanceCharge>,
 *   charges?: list<AllowanceCharge>,
 *   tax_code?: string,
 *   vatex?: string,
 *   vatex_note?: string,
 *   invoice_period?: InvoicePeriod,
 *   preceding_invoice?: PrecedingInvoice,
 * }
 *
 * @phpstan-type AllowanceCharge array{
 *   amount: float|int,
 *   reason: string,
 *   tax_rate: float|int,
 * }
 *
 * @phpstan-type InvoicePeriod array{
 *   start_date?: string,
 *   end_date?: string,
 * }
 *
 * @phpstan-type PrecedingInvoice array{
 *   number: string,
 *   issue_date?: string,
 * }
 *
 * @phpstan-type Party array{
 *   name: string,
 *   tax_id: string,
 *   address?: Address,
 *   email?: string,
 *   peppol_id?: string,
 * }
 *
 * @phpstan-type Address array{
 *   street?: string,
 *   city?: string,
 *   postal_code?: string,
 *   country?: string,
 * }
 *
 * @phpstan-type LineItem array{
 *   description: string,
 *   quantity: float|int,
 *   unit?: string,
 *   unit_price: float|int,
 *   tax_rate: float|int,
 *   discount?: float|int,
 *   allowances?: list<AllowanceCharge>,
 *   charges?: list<AllowanceCharge>,
 * }
 *
 * @phpstan-type PaymentMeans array{
 *   method?: 'bank_transfer'|'card'|'direct_debit'|'other',
 *   iban?: string,
 *   bic?: string,
 *   reference?: string,
 * }
 *
 * @phpstan-type Attachment array{
 *   filename: string,
 *   content: string,
 *   mime_type?: string,
 * }
 *
 * @phpstan-type Document array{
 *   id?: string,
 *   type?: 'invoice'|'credit_note',
 *   number?: string,
 *   status?: DocumentStatus,
 *   from?: PartyInfo,
 *   to?: PartyInfo,
 *   lines?: list<LineItem>,
 *   subtotal?: float|int,
 *   tax_total?: float|int,
 *   total?: float|int,
 *   currency?: string,
 *   issue_date?: string,
 *   due_date?: string,
 *   preceding_invoice?: PrecedingInvoice,
 *   created_at?: string,
 *   sent_at?: string|null,
 *   delivered_at?: string|null,
 * }
 *
 * @phpstan-type DocumentAccepted array{
 *   id: string,
 *   status: 'queued',
 *   url: string,
 *   credits_remaining?: int,
 * }
 *
 * @phpstan-type DocumentStatus 'queued'|'sending'|'delivered'|'failed'
 *
 * @phpstan-type PartyInfo array{
 *   name?: string,
 *   tax_id?: string,
 *   peppol_id?: string,
 * }
 *
 * @phpstan-type DocumentEvent array{
 *   event?: 'created'|'validated'|'queued'|'sending'|'delivered'|'failed',
 *   timestamp?: string,
 *   detail?: string|null,
 * }
 *
 * @phpstan-type DocumentAttachment array{
 *   id: string,
 *   filename: string,
 *   mime_type: string,
 *   size_bytes: int,
 *   sha256: string,
 *   created_at: string,
 * }
 *
 * @phpstan-type EventWithContext array{
 *   id: string,
 *   document_id: string,
 *   document_number: string,
 *   recipient: string|null,
 *   company_id: string,
 *   company_name: string,
 *   type: 'queued'|'sending'|'delivered'|'failed'|'retry',
 *   from_status?: string|null,
 *   to_status: string,
 *   message?: string|null,
 *   created_at: string,
 * }
 *
 * @phpstan-type DocumentList array{
 *   data?: list<Document>,
 *   has_more?: bool,
 *   next_cursor?: string|null,
 * }
 *
 * @phpstan-type SmpLookupResult array{
 *   participant_id?: array{
 *     scheme?: string,
 *     id?: string,
 *   },
 *   domain?: 'sml'|'smk',
 *   naptr_domain?: string,
 *   smp_url?: string,
 *   services?: list<array{
 *     document_type_id?: string,
 *     document_type_name?: string|null,
 *     process_id?: string,
 *     process_name?: string|null,
 *     endpoints?: list<array{
 *       transport_profile?: string,
 *       url?: string,
 *       certificate?: string,
 *       cert_subject?: string|null,
 *       cert_issuer?: string|null,
 *       cert_valid_from?: string|null,
 *       cert_valid_to?: string|null,
 *       cert_fingerprint_sha256?: string|null,
 *       activation_date?: string|null,
 *       expiration_date?: string|null,
 *       technical_contact?: string|null,
 *       technical_info?: string|null,
 *     }>,
 *   }>,
 *   business_card?: array{
 *     participant_id?: array{
 *       scheme?: string,
 *       id?: string,
 *     },
 *     entities?: list<array{
 *       name?: string|null,
 *       country_code?: string|null,
 *       geographic_info?: string|null,
 *       identifiers?: list<array{
 *         scheme?: string,
 *         value?: string,
 *       }>,
 *       website_urls?: list<string>,
 *       contacts?: list<array{
 *         type?: string|null,
 *         name?: string|null,
 *         phone?: string|null,
 *         email?: string|null,
 *       }>,
 *     }>,
 *   }|null,
 * }
 *
 * @phpstan-type DnsLookupResult array{
 *   peppol_id?: string,
 *   naptr_hostname?: string,
 *   smp_url?: string,
 *   domain?: 'sml'|'smk',
 * }
 *
 * @phpstan-type ValidationResult array{
 *   valid?: bool,
 *   errors?: list<ValidationIssue>,
 *   warnings?: list<ValidationIssue>,
 * }
 *
 * @phpstan-type ValidationIssue array{
 *   path?: string,
 *   code?: string,
 *   message: string,
 * }
 *
 * @phpstan-type Webhook array{
 *   id?: string,
 *   url?: string,
 *   events?: list<'document.queued'|'document.sending'|'document.delivered'|'document.failed'|'credits.low'>,
 *   active?: bool,
 *   secret?: string,
 *   created_at?: string,
 * }
 *
 * @phpstan-type WebhookCreate array{
 *   url: string,
 *   events: list<'document.queued'|'document.sending'|'document.delivered'|'document.failed'|'credits.low'>,
 *   secret?: string,
 * }
 *
 * @phpstan-type WebhookDelivery array{
 *   id?: string,
 *   webhook_id?: string,
 *   document_id?: string|null,
 *   event?: string,
 *   status?: 'pending'|'delivered'|'failed',
 *   status_code?: int|null,
 *   attempts?: int,
 *   last_error?: string|null,
 *   next_retry_at?: string|null,
 *   delivered_at?: string|null,
 *   created_at?: string,
 * }
 *
 * @phpstan-type WebhookDeliveryList array{
 *   data?: list<WebhookDelivery>,
 *   has_more?: bool,
 *   next_cursor?: string|null,
 * }
 *
 * @phpstan-type WebhookTestResponse array{
 *   delivery_id?: string,
 *   success?: bool,
 *   status_code?: int|null,
 *   error?: string|null,
 * }
 *
 * @phpstan-type WebhookRotateSecretResponse array{
 *   id?: string,
 *   url?: string,
 *   secret?: string,
 *   secret_overlap_expires_at?: int,
 * }
 *
 * @phpstan-type ErrorObject array{
 *   error: array{
 *     type: 'validation_error'|'authentication_error'|'authorization_error'|'not_found'|'rate_limit_error'|'provider_error'|'billing_error'|'internal_error',
 *     code: string,
 *     message: string,
 *     param?: string,
 *     details?: mixed,
 *   },
 * }
 *
 * @phpstan-type GetProtectedResourceMetadataResponse array{
 *   resource: string,
 *   bearer_methods_supported: list<'header'>,
 *   resource_documentation: string,
 * }
 *
 * @phpstan-type GetHealthResponse HealthResponse
 *
 * @phpstan-type ListWorkspacesResponse array{
 *   data?: list<Workspace>,
 * }
 *
 * @phpstan-type CreateWorkspaceParams array{
 *   name: string,
 * }
 *
 * @phpstan-type CreateWorkspaceResponse Workspace
 *
 * @phpstan-type GetWorkspaceResponse Workspace
 *
 * @phpstan-type DeleteWorkspaceResponse array{
 *   deleted?: bool,
 * }
 *
 * @phpstan-type UpdateWorkspaceParams array{
 *   name?: string,
 * }
 *
 * @phpstan-type UpdateWorkspaceResponse Workspace
 *
 * @phpstan-type ListWorkspaceMembersResponse array{
 *   data?: list<WorkspaceMember>,
 * }
 *
 * @phpstan-type InviteWorkspaceMemberParams array{
 *   account_id?: string,
 *   email?: string,
 *   role?: 'owner'|'admin'|'member',
 * }
 *
 * @phpstan-type InviteWorkspaceMemberResponse array{
 *   account_id: string,
 *   workspace_id: string,
 *   role: 'owner'|'admin'|'member',
 * }
 *
 * @phpstan-type RemoveWorkspaceMemberResponse array{
 *   removed: true,
 *   account_id: string,
 * }
 *
 * @phpstan-type ChangeWorkspaceMemberRoleParams array{
 *   role: 'owner'|'admin'|'member',
 * }
 *
 * @phpstan-type ChangeWorkspaceMemberRoleResponse array{
 *   account_id: string,
 *   role: 'owner'|'admin'|'member',
 * }
 *
 * @phpstan-type TransferWorkspaceOwnershipParams array{
 *   account_id: string,
 * }
 *
 * @phpstan-type TransferWorkspaceOwnershipResponse array{
 *   transferred: true,
 *   new_owner: string,
 * }
 *
 * @phpstan-type SignupParams array{
 *   email: string,
 *   name?: string,
 * }
 *
 * @phpstan-type GetAccountResponse Account
 *
 * @phpstan-type CreateKeyParams array{
 *   label?: string,
 *   sandbox?: bool,
 * }
 *
 * @phpstan-type CreateKeyResponse ApiKeyCreated
 *
 * @phpstan-type RevokeKeyResponse array{
 *   revoked?: bool,
 *   prefix?: string,
 * }
 *
 * @phpstan-type ListAccountAuditEventsQuery array{
 *   limit?: int,
 *   cursor?: string,
 * }
 *
 * @phpstan-type ListAccountAuditEventsResponse array{
 *   data?: list<AuditEvent>,
 *   has_more?: bool,
 *   next_cursor?: string|null,
 * }
 *
 * @phpstan-type ListWorkspaceAuditEventsQuery array{
 *   limit?: int,
 *   cursor?: string,
 * }
 *
 * @phpstan-type ListWorkspaceAuditEventsResponse array{
 *   data?: list<AuditEvent>,
 *   has_more?: bool,
 *   next_cursor?: string|null,
 * }
 *
 * @phpstan-type GetUsageQuery array{
 *   days?: int,
 * }
 *
 * @phpstan-type GetUsageResponse UsageResponse
 *
 * @phpstan-type GetKycResponse KycState
 *
 * @phpstan-type UploadKycDocumentParams KycDocumentUpload
 *
 * @phpstan-type UploadKycDocumentResponse KycDocument
 *
 * @phpstan-type SubmitKycParams KycSubmit
 *
 * @phpstan-type SubmitKycResponse KycState
 *
 * @phpstan-type ListCompaniesResponse array{
 *   data?: list<CompanyListItem>,
 * }
 *
 * @phpstan-type CreateCompanyParams CompanyCreate
 *
 * @phpstan-type CreateCompanyResponse Company
 *
 * @phpstan-type GetCompanyResponse CompanyDetail
 *
 * @phpstan-type UpdateCompanyParams CompanyUpdate
 *
 * @phpstan-type UpdateCompanyResponse CompanyDetail
 *
 * @phpstan-type ListDocumentsQuery array{
 *   company_id: string,
 *   status?: DocumentStatus,
 *   limit?: int,
 *   cursor?: string,
 * }
 *
 * @phpstan-type ListDocumentsResponse DocumentList
 *
 * @phpstan-type SendDocumentParams array{
 *   type?: 'invoice'|'credit_note',
 *   number: string,
 *   issue_date: string,
 *   due_date?: string,
 *   currency?: string,
 *   from: Party,
 *   to: Party,
 *   lines: list<LineItem>,
 *   note?: string,
 *   payment_means?: PaymentMeans,
 *   attachments?: list<Attachment>,
 *   idempotency_key?: string,
 *   buyer_reference?: string,
 *   allowances?: list<AllowanceCharge>,
 *   charges?: list<AllowanceCharge>,
 *   tax_code?: string,
 *   vatex?: string,
 *   vatex_note?: string,
 *   invoice_period?: InvoicePeriod,
 *   preceding_invoice?: PrecedingInvoice,
 *   company_id: string,
 * }
 *
 * @phpstan-type SendDocumentResponse DocumentAccepted
 *
 * @phpstan-type SendDocumentBatchParams list<array{
 *   type?: 'invoice'|'credit_note',
 *   number: string,
 *   issue_date: string,
 *   due_date?: string,
 *   currency?: string,
 *   from: Party,
 *   to: Party,
 *   lines: list<LineItem>,
 *   note?: string,
 *   payment_means?: PaymentMeans,
 *   attachments?: list<Attachment>,
 *   idempotency_key?: string,
 *   buyer_reference?: string,
 *   allowances?: list<AllowanceCharge>,
 *   charges?: list<AllowanceCharge>,
 *   tax_code?: string,
 *   vatex?: string,
 *   vatex_note?: string,
 *   invoice_period?: InvoicePeriod,
 *   preceding_invoice?: PrecedingInvoice,
 *   company_id: string,
 * }>
 *
 * @phpstan-type SendDocumentBatchResponse list<DocumentAccepted|ErrorObject>
 *
 * @phpstan-type GetDocumentQuery array{
 *   company_id: string,
 * }
 *
 * @phpstan-type GetDocumentResponse Document
 *
 * @phpstan-type GetDocumentHistoryResponse list<DocumentEvent>
 *
 * @phpstan-type GetDocumentUblResponse string
 *
 * @phpstan-type ListDocumentAttachmentsResponse array{
 *   data?: list<DocumentAttachment>,
 * }
 *
 * @phpstan-type GetDocumentAttachmentResponse string
 *
 * @phpstan-type ListEventsQuery array{
 *   company_id?: string,
 *   type?: 'queued'|'sending'|'delivered'|'failed'|'retry',
 *   from?: string,
 *   to?: string,
 *   document_id?: string,
 *   q?: string,
 *   cursor?: string,
 *   limit?: int,
 * }
 *
 * @phpstan-type ListEventsResponse array{
 *   data: list<EventWithContext>,
 *   has_more: bool,
 *   next_cursor: string|null,
 * }
 *
 * @phpstan-type LookupParticipantQuery array{
 *   domain?: 'sml'|'smk',
 * }
 *
 * @phpstan-type LookupParticipantResponse SmpLookupResult
 *
 * @phpstan-type LookupParticipantDnsQuery array{
 *   domain?: 'sml'|'smk',
 * }
 *
 * @phpstan-type LookupParticipantDnsResponse DnsLookupResult
 *
 * @phpstan-type ValidateDocumentParams array{
 *   type?: 'invoice'|'credit_note',
 *   number: string,
 *   issue_date: string,
 *   due_date?: string,
 *   currency?: string,
 *   from: Party,
 *   to: Party,
 *   lines: list<LineItem>,
 *   note?: string,
 *   payment_means?: PaymentMeans,
 *   attachments?: list<Attachment>,
 *   idempotency_key?: string,
 *   buyer_reference?: string,
 *   allowances?: list<AllowanceCharge>,
 *   charges?: list<AllowanceCharge>,
 *   tax_code?: string,
 *   vatex?: string,
 *   vatex_note?: string,
 *   invoice_period?: InvoicePeriod,
 *   preceding_invoice?: PrecedingInvoice,
 *   company_id: string,
 * }
 *
 * @phpstan-type ValidateDocumentResponse ValidationResult
 *
 * @phpstan-type ListWebhooksResponse array{
 *   data: list<Webhook>,
 * }
 *
 * @phpstan-type CreateWebhookParams WebhookCreate
 *
 * @phpstan-type CreateWebhookResponse Webhook
 *
 * @phpstan-type GetWebhookResponse Webhook
 *
 * @phpstan-type DeleteWebhookResponse array{
 *   deleted: bool,
 * }
 *
 * @phpstan-type ListWebhookDeliveriesQuery array{
 *   limit?: int,
 *   cursor?: string,
 * }
 *
 * @phpstan-type ListWebhookDeliveriesResponse WebhookDeliveryList
 *
 * @phpstan-type TestWebhookResponse WebhookTestResponse
 *
 * @phpstan-type RotateWebhookSecretResponse WebhookRotateSecretResponse
 */
final class Types
{
    private function __construct()
    {
    }
}
