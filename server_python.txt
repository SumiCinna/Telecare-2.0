from flask import Flask, request, jsonify
from flask_cors import CORS
import whisper
import tempfile
import os
import subprocess

app = Flask(__name__)
CORS(app)

print("Loading Whisper model (tiny)... please wait...")
model = whisper.load_model("tiny")
print("Whisper ready! Using tiny model for fast transcription.")

@app.route('/transcribe', methods=['POST'])
def transcribe():
    if 'audio' not in request.files:
        return jsonify({'error': 'No audio file'}), 400

    audio_file = request.files['audio']
    with tempfile.NamedTemporaryFile(suffix='.webm', delete=False) as tmp_webm:
        audio_file.save(tmp_webm.name)
        webm_path = tmp_webm.name

    wav_path = webm_path.replace('.webm', '.wav')
    try:
        subprocess.run([
            'ffmpeg', '-i', webm_path,
            '-ar', '16000', '-ac', '1', '-c:a', 'pcm_s16le',
            wav_path, '-y', '-loglevel', 'quiet'
        ], check=True, timeout=30)
        transcribe_path = wav_path
    except Exception:
        transcribe_path = webm_path

    try:
        result = model.transcribe(
            transcribe_path,
            language='en',
            fp16=False,
            temperature=0,
            condition_on_previous_text=False
        )
        text = result['text'].strip()
    except Exception as e:
        text = ''
        print(f"Transcription error: {e}")
    finally:
        try: os.unlink(webm_path)
        except: pass
        try: os.unlink(wav_path)
        except: pass

    print(f"Transcribed: {text[:80]}")
    return jsonify({'text': text})

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'model': 'whisper-tiny'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=9000, debug=False)