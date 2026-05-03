<div style="{{ $style ?? 'max-height: 90vh; overflow-y: auto;' }}">
    <iframe src="data:application/pdf;base64,{{ base64_encode($pdf) }}" 
            width="100%" 
            height="800px" 
            style="border: none;">
    </iframe>
</div>
