from flask import Flask, request, jsonify
from transformers import AutoTokenizer, MBartForConditionalGeneration
import torch
import os

app = Flask(__name__)

# Đường dẫn model
MODEL_DIR = "./models"

# Load model và tokenizer
print("Loading model...")
try:
    tokenizer = AutoTokenizer.from_pretrained(MODEL_DIR)
    model = MBartForConditionalGeneration.from_pretrained(MODEL_DIR)
    print("Model loaded successfully!")
except Exception as e:
    print(f"Error loading model: {e}")

def kiem_duyet_don_gian(text):
    """Hàm kiểm duyệt cơ bản"""
    tu_cam = ["bạo lực", "phản động", "spam"]
    if len(text.strip()) < 10:  # Text quá ngắn
        return False
    if any(word in text.lower() for word in tu_cam):
        return False
    return True

@app.route('/health', methods=['GET'])
def health_check():
    """Endpoint kiểm tra API hoạt động"""
    return jsonify({"status": "OK", "message": "AI Service is running"})

@app.route('/process', methods=['POST'])
def process_document():
    """Endpoint xử lý kiểm duyệt và tóm tắt"""
    try:
        data = request.json
        text = data.get('text', '')
        lang = data.get('lang', 'vi_VN')  # Mặc định tiếng Việt
        
        # Bước 1: Kiểm duyệt
        if not kiem_duyet_don_gian(text):
            return jsonify({
                "success": False,
                "check_passed": False,
                "message": "Tài liệu không đạt kiểm duyệt",
                "summary": None
            })
        
        # Bước 2: Tóm tắt (nếu qua kiểm duyệt)
        # Giới hạn độ dài input để tránh quá tải
        max_length = 2500
        if len(text.split()) > max_length:
            text = ' '.join(text.split()[:max_length])
        
        # Setup tokenizer cho ngôn ngữ đầu vào
        tokenizer.src_lang = lang
        
        # Tokenize và tóm tắt
        inputs = tokenizer(text, return_tensors="pt", max_length=512, truncation=True)
        
        # Generate summary
        with torch.no_grad():
            summary_ids = model.generate(inputs.input_ids, max_length=256, min_length=20)

        
        summary = tokenizer.batch_decode(summary_ids, skip_special_tokens=True)[0]
        
        return jsonify({
            "success": True,
            "check_passed": True,
            "message": "Xử lý thành công",
            "summary": summary
        })
        
    except Exception as e:
        return jsonify({
            "success": False,
            "error": str(e),
            "message": "Lỗi xử lý tài liệu"
        })

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=True)
