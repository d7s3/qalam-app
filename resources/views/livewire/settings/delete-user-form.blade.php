<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>حذف الحساب</flux:heading>
        <flux:subheading>حذف حسابك وجميع بياناته المرتبطة به</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            حذف الحساب
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">هل أنت متأكد من رغبتك في حذف حسابك؟</flux:heading>

                <flux:subheading>
                    بمجرد حذف حسابك، سيتم حذف جميع بياناته وموارده بشكل نهائي. من فضلك أدخل كلمة المرور لتأكيد رغبتك في حذف الحساب نهائيًا.
                </flux:subheading>
            </div>

            <flux:input wire:model="password" label="كلمة المرور" type="password" viewable />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">إلغاء</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">حذف الحساب</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
