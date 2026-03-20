import sys

def read_pdf(file_path):
    try:
        import PyPDF2
        reader = PyPDF2.PdfReader(file_path)
        print("\n".join([p.extract_text() for p in reader.pages]))
        return
    except ImportError:
        pass

    try:
        import fitz
        doc = fitz.open(file_path)
        print("\n".join([page.get_text() for page in doc]))
        return
    except ImportError:
        pass
        
    print("No PDF library installed")

if __name__ == '__main__':
    read_pdf('facturx_champs_obligatoires.pdf')
