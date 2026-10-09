@extends('layouts.admin')

@section('title', 'Asisten AI')
@section('heading', 'Asisten Ketersediaan Barang')

@section('content')
    <p class="text-muted">
        Tanyakan ketersediaan barang pada tanggal tertentu atau cara meminjam.
        Saya tidak menjawab hal di luar itu.
        <span class="badge bg-secondary">{{ $mode === 'llm' ? 'Mode AI' : 'Mode aturan' }}</span>
    </p>

    <div class="card">
        <div id="chat" class="card-body" style="height: 420px; overflow-y: auto; background: #f8f9fa;">
            @forelse ($history as $row)
                <div class="d-flex justify-content-end mb-2">
                    <div class="bg-primary text-white rounded px-3 py-2" style="max-width: 80%; white-space: pre-line;">{{ $row->question }}</div>
                </div>
                <div class="d-flex justify-content-start mb-3">
                    <div class="bg-white border rounded px-3 py-2" style="max-width: 80%; white-space: pre-line;">{{ $row->answer }}</div>
                </div>
            @empty
                <div class="d-flex justify-content-start mb-3" id="welcome">
                    <div class="bg-white border rounded px-3 py-2" style="max-width: 80%; white-space: pre-line;">Halo! Tanyakan ketersediaan barang, misalnya: "Apakah proyektor tersedia besok?"</div>
                </div>
            @endforelse
        </div>

        <div class="card-footer">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-secondary suggest">Apakah proyektor tersedia besok?</button>
                <button type="button" class="btn btn-sm btn-outline-secondary suggest">Barang apa saja yang tersedia hari ini?</button>
                <button type="button" class="btn btn-sm btn-outline-secondary suggest">Cara meminjam barang</button>
            </div>

            <form id="chat-form" class="d-flex gap-2" autocomplete="off">
                <input type="text" id="question" class="form-control" maxlength="300"
                       placeholder="Tulis pertanyaan, mis. Kamera tersedia tanggal 15 Oktober?" required>
                <button type="submit" id="send" class="btn btn-primary">Kirim</button>
            </form>
        </div>
    </div>

    @if ($history->isNotEmpty())
        <form method="POST" action="{{ route('assistant.clear') }}" class="mt-3"
              onsubmit="return confirm('Hapus seluruh riwayat percakapan?')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus riwayat</button>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var chat = document.getElementById('chat');
        var form = document.getElementById('chat-form');
        var input = document.getElementById('question');
        var send = document.getElementById('send');
        var token = document.querySelector('meta[name="csrf-token"]').content;

        chat.scrollTop = chat.scrollHeight;

        function bubble(text, mine) {
            var row = document.createElement('div');
            row.className = 'd-flex mb-3 ' + (mine ? 'justify-content-end' : 'justify-content-start');
            var box = document.createElement('div');
            box.className = (mine ? 'bg-primary text-white' : 'bg-white border') + ' rounded px-3 py-2';
            box.style.maxWidth = '80%';
            box.style.whiteSpace = 'pre-line';
            box.textContent = text;
            row.appendChild(box);
            chat.appendChild(row);
            chat.scrollTop = chat.scrollHeight;
            return box;
        }

        function ask(question) {
            var welcome = document.getElementById('welcome');
            if (welcome) { welcome.remove(); }

            bubble(question, true);
            var waiting = bubble('Sedang memeriksa...', false);
            send.disabled = true;

            fetch('{{ route('assistant.ask') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ question: question })
            }).then(function (res) {
                return res.json().then(function (body) { return { status: res.status, body: body }; });
            }).then(function (r) {
                if (r.status === 429) {
                    waiting.textContent = 'Terlalu banyak pertanyaan. Tunggu sebentar lalu coba lagi.';
                } else if (r.status === 422) {
                    var first = r.body.errors ? Object.values(r.body.errors)[0][0] : 'Pertanyaan tidak valid.';
                    waiting.textContent = first;
                } else if (r.status >= 400) {
                    waiting.textContent = 'Maaf, terjadi kesalahan. Coba lagi nanti.';
                } else {
                    waiting.textContent = r.body.answer;
                    (r.body.links || []).forEach(function (link) {
                        var a = document.createElement('a');
                        a.href = link.url;
                        a.className = 'btn btn-sm btn-outline-primary d-block mt-2';
                        a.textContent = link.label;
                        waiting.appendChild(a);
                    });
                }
            }).catch(function () {
                waiting.textContent = 'Maaf, tidak dapat terhubung ke server.';
            }).finally(function () {
                send.disabled = false;
                chat.scrollTop = chat.scrollHeight;
                input.focus();
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var q = input.value.trim();
            if (!q) { return; }
            input.value = '';
            ask(q);
        });

        document.querySelectorAll('.suggest').forEach(function (btn) {
            btn.addEventListener('click', function () { ask(btn.textContent); });
        });
    })();
</script>
@endpush
