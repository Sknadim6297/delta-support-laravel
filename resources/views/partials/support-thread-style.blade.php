<style>
    .ds-ticket-thread { display: flex; flex-direction: column; gap: 12px; }
    .ds-ticket-message { border: 1px solid #e6eef6; border-radius: 12px; padding: 12px 14px; background: #fff; }
    .ds-ticket-message.is-admin { background: #f4f9fd; }
    .ds-ticket-meta { margin: 0 0 8px; color: #5c6b7a; font-size: 12px; }
    .ds-ticket-message p { margin: 0 0 8px; white-space: pre-wrap; overflow-wrap: anywhere; }
    .ds-ticket-message p:last-child { margin-bottom: 0; }
    .ds-ticket-reply textarea,
    .ds-ticket-reply input[type="file"],
    .ds-ticket-reply select { width: 100%; max-width: 100%; }
    @media (max-width: 700px) {
        .ds-ticket-reply .ds-form-actions,
        .ds-ticket-reply button { width: 100%; }
    }
</style>
