@if ($editingVehicle)
    <div
        x-data
        x-init="$nextTick(() => $refs.plateNumber?.focus())"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-gray-950/50" wire:click="cancelEditVehicle"></div>

        <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Edit vehicle</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Update this rider's vehicle details.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-filament-forms::field-wrapper label="Type" id="vehicleTypeId" statePath="vehicleTypeId" :required="true">
                    <x-filament::input.wrapper :valid="! $errors->has('vehicleTypeId')">
                        <x-filament::input.select wire:model="vehicleTypeId" id="vehicleTypeId">
                            @foreach ($this->vehicleTypeOptions as $vehicleType)
                                <option value="{{ $vehicleType->id }}">{{ $vehicleType->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper label="Model" id="vehicleModelId" statePath="vehicleModelId">
                    <x-filament::input.wrapper :valid="! $errors->has('vehicleModelId')">
                        <x-filament::input.select wire:model="vehicleModelId" id="vehicleModelId">
                            <option value="">—</option>
                            @foreach ($this->vehicleModelOptions as $vehicleModel)
                                <option value="{{ $vehicleModel->id }}">{{ $vehicleModel->make }} {{ $vehicleModel->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper label="Plate number" id="plateNumber" statePath="plateNumber" :required="true">
                    <x-filament::input.wrapper :valid="! $errors->has('plateNumber')">
                        <x-filament::input
                            x-ref="plateNumber"
                            type="text"
                            wire:model="plateNumber"
                            id="plateNumber"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper label="Registration number" id="registrationNumber" statePath="registrationNumber">
                    <x-filament::input.wrapper :valid="! $errors->has('registrationNumber')">
                        <x-filament::input
                            type="text"
                            wire:model="registrationNumber"
                            id="registrationNumber"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper label="Year" id="year" statePath="year">
                    <x-filament::input.wrapper :valid="! $errors->has('year')">
                        <x-filament::input
                            type="number"
                            wire:model="year"
                            id="year"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper label="Color" id="color" statePath="color">
                    <x-filament::input.wrapper :valid="! $errors->has('color')">
                        <x-filament::input
                            type="text"
                            wire:model="color"
                            id="color"
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <div class="sm:col-span-2">
                    <x-filament-forms::field-wrapper label="Insurance expiry" id="insuranceExpiryAt" statePath="insuranceExpiryAt">
                        <x-filament::input.wrapper :valid="! $errors->has('insuranceExpiryAt')">
                            <x-filament::input
                                type="date"
                                wire:model="insuranceExpiryAt"
                                id="insuranceExpiryAt"
                            />
                        </x-filament::input.wrapper>
                    </x-filament-forms::field-wrapper>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <x-filament::button color="gray" outlined wire:click="cancelEditVehicle">
                    Cancel
                </x-filament::button>
                <x-filament::button wire:click="saveVehicle">
                    Save changes
                </x-filament::button>
            </div>
        </div>
    </div>
@endif
