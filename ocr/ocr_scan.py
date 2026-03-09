# ocr_scan.py
import sys
import os
import json
import pytesseract
import cv2
import numpy as np
from PIL import Image
from pdf2image import convert_from_path

# ── Point to Tesseract install location
pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# ── Point to Poppler bin
POPPLER_PATH = r'C:\poppler-25.12.0\Library\bin'

def preprocess_image(pil_image):
    """Clean up image for better OCR accuracy — handles colored text like red warnings"""
    img = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)

    # Boost colored text (red, blue) before grayscale conversion
    # Red text goes light in grayscale — darken it first by boosting red channel contrast
    lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
    l, a, b = cv2.split(lab)
    # Boost a-channel (red/green axis) so red text stands out more
    a = cv2.add(a, 30)
    lab = cv2.merge([l, a, b])
    img = cv2.cvtColor(lab, cv2.COLOR_LAB2BGR)

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    denoised = cv2.fastNlMeansDenoising(gray, h=10)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    contrasted = clahe.apply(denoised)
    _, thresh = cv2.threshold(contrasted, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return thresh

def extract_text_from_image(image_path):
    """Run OCR on an image file"""
    pil_image = Image.open(image_path).convert('RGB')
    processed = preprocess_image(pil_image)
    custom_config = r'--oem 3 --psm 6'
    text = pytesseract.image_to_string(processed, config=custom_config)
    return text.strip()

def extract_text_from_pdf(pdf_path):
    """Convert PDF pages to images then run OCR on each page"""
    pages = convert_from_path(pdf_path, dpi=300, poppler_path=POPPLER_PATH)
    all_text = []
    for i, page in enumerate(pages):
        processed = preprocess_image(page)
        custom_config = r'--oem 3 --psm 6'
        text = pytesseract.image_to_string(processed, config=custom_config)
        if text.strip():
            all_text.append(f"--- Page {i+1} ---\n{text.strip()}")
    return '\n\n'.join(all_text)

def classify_document(text):
    """Guess if this is a lab result or a prescription based on keywords"""
    text_lower = text.lower()
    lab_keywords = ['result', 'hemoglobin', 'wbc', 'rbc', 'platelet', 'glucose',
                    'cholesterol', 'creatinine', 'uric acid', 'hematocrit',
                    'sodium', 'potassium', 'laboratory', 'normal range', 'reference']
    rx_keywords  = ['prescription', 'sig:', 'rx', 'dispense', 'refill', 'tablet',
                    'capsule', 'mg', 'ml', 'take', 'once daily', 'twice daily',
                    'morning', 'bedtime', 'amoxicillin', 'metformin', 'losartan']
    lab_score = sum(1 for kw in lab_keywords if kw in text_lower)
    rx_score  = sum(1 for kw in rx_keywords  if kw in text_lower)
    if lab_score > rx_score:
        return 'lab_result'
    elif rx_score > lab_score:
        return 'prescription'
    else:
        return 'unknown'

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file path provided"}))
        sys.exit(1)

    file_path = sys.argv[1]

    if not os.path.exists(file_path):
        print(json.dumps({"success": False, "error": f"File not found: {file_path}"}))
        sys.exit(1)

    try:
        ext = os.path.splitext(file_path)[1].lower()

        if ext == '.pdf':
            text = extract_text_from_pdf(file_path)
        else:
            text = extract_text_from_image(file_path)

        doc_type = classify_document(text)

        result = {
            "success":    True,
            "text":       text,
            "type":       doc_type,
            "char_count": len(text),
            "filename":   os.path.basename(file_path)
        }
        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()