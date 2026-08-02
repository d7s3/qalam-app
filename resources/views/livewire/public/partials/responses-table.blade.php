<div class="overflow-x-auto rounded-xl border border-zinc-200">
    <table class="w-full text-start border-collapse text-sm">
        <thead>
            <tr class="bg-zinc-50 text-zinc-700 font-semibold border-b border-zinc-200">
                <th class="p-4 text-start min-w-[120px]">تاريخ الرد</th>
                @foreach($activeFields as $field)
                    <th class="p-4 text-start min-w-[120px]">{{ $field['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-150">
            @foreach($responses as $response)
                <tr class="hover:bg-zinc-50/50 text-zinc-850">
                    <td class="p-4 whitespace-nowrap text-xs text-zinc-450">
                        <x-hijri-date :date="$response->created_at" style="withTime" />
                    </td>
                    @foreach($activeFields as $field)
                        @php
                            $fieldId = $field['id'];
                            $answer = $response->answers[$fieldId] ?? null;
                        @endphp
                        <td class="p-4">
                            @if($field['type'] === 'image' && $answer)
                                <a href="{{ asset('storage/' . $answer) }}" target="_blank" class="block w-10 h-10 rounded-lg overflow-hidden border border-zinc-200 hover:scale-105 transition-transform">
                                    <img src="{{ asset('storage/' . $answer) }}" class="w-full h-full object-cover" />
                                </a>
                            @elseif($field['type'] === 'multiselect' && is_array($answer))
                                <div class="flex flex-wrap gap-1">
                                    @foreach($answer as $opt)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 text-zinc-700 font-medium">
                                            {{ $opt }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="line-clamp-2" title="{{ is_array($answer) ? implode(', ', $answer) : $answer }}">
                                    {{ is_array($answer) ? implode(', ', $answer) : $answer }}
                                </span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
