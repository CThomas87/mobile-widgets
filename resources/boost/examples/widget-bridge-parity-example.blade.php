<div class="space-y-2">
    <button wire:click="executeAlias">execute (alias)</button>
    <button wire:click="setData">setData (payload)</button>
    <button wire:click="configure">configure (style/schedule)</button>
    <button wire:click="reloadAll">reloadAll</button>
    <button wire:click="getStatus">getStatus</button>

    <pre>{{ json_encode($status ?? [], JSON_PRETTY_PRINT) }}</pre>
</div>
