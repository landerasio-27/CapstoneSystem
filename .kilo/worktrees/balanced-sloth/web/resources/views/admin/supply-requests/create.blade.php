@extends('layouts.admin')

@section('title', 'New Supply Request')

@section('content')
    <div class="max-w-3xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <form method="POST" action="{{ route('admin.supply-requests.store') }}">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Requested By</label>
                    <select name="requested_by" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('requested_by') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('requested_by') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Purpose</label>
                    <textarea name="purpose" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('purpose') }}</textarea>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">Requested Items</h3>
                    <button type="button" onclick="addItemRow()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">+ Add Item</button>
                </div>

                <div id="item-rows" class="space-y-2">
                    <div class="item-row grid grid-cols-12 gap-2">
                        <select name="items[0][supply_id]" required class="col-span-8 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Select supply</option>
                            @foreach ($supplies as $supply)
                                <option value="{{ $supply->id }}">{{ $supply->name }} ({{ $supply->quantity_on_hand }} {{ $supply->unit }} available)</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" name="items[0][quantity_requested]" placeholder="Qty" required class="col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button type="button" onclick="this.closest('.item-row').remove()" class="col-span-1 text-red-500">&times;</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin.supply-requests.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="x-mark" class="h-4 w-4" /> Cancel</a>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900"><x-icon name="check" class="h-4 w-4" /> Submit Request</button>
            </div>
        </form>
    </div>

    <template id="item-row-template">
        <div class="item-row grid grid-cols-12 gap-2">
            <select name="items[__INDEX__][supply_id]" required class="col-span-8 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select supply</option>
                @foreach ($supplies as $supply)
                    <option value="{{ $supply->id }}">{{ $supply->name }} ({{ $supply->quantity_on_hand }} {{ $supply->unit }} available)</option>
                @endforeach
            </select>
            <input type="number" min="1" name="items[__INDEX__][quantity_requested]" placeholder="Qty" required class="col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="button" onclick="this.closest('.item-row').remove()" class="col-span-1 text-red-500">&times;</button>
        </div>
    </template>

    <script>
        let itemIndex = 1;
        function addItemRow() {
            const template = document.getElementById('item-row-template').innerHTML.replaceAll('__INDEX__', itemIndex++);
            document.getElementById('item-rows').insertAdjacentHTML('beforeend', template);
        }
    </script>
@endsection
