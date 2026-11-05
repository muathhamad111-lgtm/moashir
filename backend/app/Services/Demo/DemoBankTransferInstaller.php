<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Finance\CreateBankAccount;
use App\Data\Demo\DemoUserAccount;
use App\Data\Files\GeneratedFile;
use App\Data\Finance\BankTransferSubmission;
use App\Data\Finance\NewBankAccount;
use App\Data\Finance\TransferRejection;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\TransferRejectionReason;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Entities\Entity;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Files\StoredFileWriter;
use App\Services\Finance\BankTransferApproval;
use App\Services\Finance\BankTransferOrderOpener;
use App\Services\Finance\BankTransferRejection;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\ServicePriceQuotes;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\Iban;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DemoBankTransferInstaller
{
    public const string EXAMPLE_IBAN = 'SA0380000000608010167519';

    private const string RECEIPT_NAME = 'transfer-receipt-example.pdf';

    private const string RECEIPT_CONTENTS = "%PDF-1.4\n%Moashir demo bank transfer receipt (example, no real account)\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    public function __construct(
        private CreateBankAccount $accounts,
        private SettingsStore $settings,
        private StoredFileWriter $files,
        private ServicePriceQuotes $quotes,
        private FinanceSettings $financeSettings,
        private BankTransferOrderOpener $opener,
        private BankTransferApproval $approval,
        private BankTransferRejection $rejection,
    ) {}

    /**
     * @return list<Payment>
     */
    public function install(User $systemAdmin, User $financeOfficer, DemoUserAccount $pendingOwner, DemoUserAccount $settledOwner): array
    {
        $account = $this->exampleAccount($systemAdmin);
        $this->settings->put(SettingKey::PaymentChannels, [PaymentChannel::Electronic->value, PaymentChannel::BankTransfer->value], AuditEvent::PaymentChannelsUpdated, $systemAdmin);

        $rejected = $this->rejection->reject($financeOfficer, $this->submit($settledOwner, $account), new TransferRejection(TransferRejectionReason::NotReceived, null));
        $paid = $this->approval->approve($financeOfficer, $this->submit($settledOwner, $account));

        return [$rejected, $paid, $this->submit($pendingOwner, $account)];
    }

    private function exampleAccount(User $systemAdmin): BankAccount
    {
        return $this->accounts->handle($systemAdmin, new NewBankAccount(
            LocalizedText::fromTranslation('finance.demo_bank_account.bank_name'),
            LocalizedText::fromTranslation('finance.demo_bank_account.account_holder_name'),
            new Iban(self::EXAMPLE_IBAN),
            null,
            LocalizedText::fromTranslation('finance.demo_bank_account.payer_note'),
        ), null);
    }

    private function submit(DemoUserAccount $owner, BankAccount $account): Payment
    {
        $entity = Entity::query()->findOrFail($owner->user->entity_id);
        $quote = $this->quotes->quoteOf(PaymentPurpose::Accredited, $this->financeSettings->vatRate())
            ?? throw DemoDataRefused::missingPrerequisite('the accredited assessment price is not set');
        $proof = $this->files->storeGenerated(
            new GeneratedFile(StoredFilePurpose::TransferProof, self::RECEIPT_CONTENTS, self::RECEIPT_NAME, 'pdf', 'application/pdf'),
            $owner->user,
        );
        $submission = new BankTransferSubmission(PaymentPurpose::Accredited, Str::uuid()->toString(), $account->public_id, $quote, $proof);

        return DB::transaction(fn (): Payment => $this->opener->open($owner->user, $entity, $submission));
    }
}
