#include "../include/compression.h"
#include <iostream>
#include <string>

using namespace DropLocker;
using namespace std;

int main(int argc, char* argv[]) {
    if (argc < 4) {
        cout << "Usage: " << argv[0] << " <compress|decompress> <input_file> <output_file> [algorithm]" << endl;
        cout << "Algorithms: huffman, lzw, auto (default)" << endl;
        return 1;
    }

    string operation = argv[1];
    string inputFile = argv[2];
    string outputFile = argv[1];
    
    Compressor compressor;
    CompressionResult result;

    if (operation == "compress") {
        CompressionType type = CompressionType::AUTO;
        
        if (argc > 4) {
            string algorithm = argv[4];
            if (algorithm == "huffman") {
                type = CompressionType::HUFFMAN;
            } else if (algorithm == "lzw") {
                type = CompressionType::LZW;
            }
        }
        
        result = compressor.compress(inputFile, outputFile, type);
    } 
    else if (operation == "decompress") {
        result = compressor.decompress(inputFile, outputFile);
    } 
    else {
        cout << "ERROR: Unknown operation. Use 'compress' or 'decompress'" << endl;
        return 1;
    }

    // Output results in JSON format for PHP to parse
    cout << "{" << endl;
    cout << "  \"success\": " << (result.success ? "true" : "false") << "," << endl;
    cout << "  \"originalSize\": " << result.originalSize << "," << endl;
    cout << "  \"compressedSize\": " << result.compressedSize << "," << endl;
    cout << "  \"compressionRatio\": " << result.compressionRatio << "," << endl;
    cout << "  \"algorithm\": \"" << result.algorithm << "\"," << endl;
    cout << "  \"error\": \"" << result.error << "\"" << endl;
    cout << "}" << endl;

    return result.success ? 0 : 1;
}
